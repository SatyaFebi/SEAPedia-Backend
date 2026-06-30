<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createDriverUser(string $name, string $username)
{
    $user = User::create([
        'id' => (string) Str::uuid(),
        'name' => $name,
        'username' => $username,
        'email' => $username.'@test.com',
        'password' => bcrypt('password123'),
        'api_token' => 'token_'.$username,
    ]);

    UserRole::create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'role' => 'Driver',
    ]);

    return $user;
}

function createSellerAndStore()
{
    $seller = User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Seller Udin',
        'username' => 'udin',
        'email' => 'udin@test.com',
        'password' => bcrypt('password123'),
    ]);
    UserRole::create([
        'id' => (string) Str::uuid(),
        'user_id' => $seller->id,
        'role' => 'Seller',
    ]);

    $store = Store::create([
        'id' => (string) Str::uuid(),
        'user_id' => $seller->id,
        'store_name' => 'Udin Store',
    ]);

    return [$seller, $store];
}

function createBuyerUser()
{
    $buyer = User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Buyer Joko',
        'username' => 'joko',
        'email' => 'joko@test.com',
        'password' => bcrypt('password123'),
    ]);
    UserRole::create([
        'id' => (string) Str::uuid(),
        'user_id' => $buyer->id,
        'role' => 'Buyer',
    ]);

    return $buyer;
}

test('driver can see available jobs with status Menunggu Pengirim', function () {
    $driver = createDriverUser('Driver Agus', 'agus');
    $buyer = createBuyerUser();
    [$seller, $store] = createSellerAndStore();

    // Create eligible order (Menunggu Pengirim)
    $orderEligible = Order::create([
        'id' => (string) Str::uuid(),
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'delivery_method' => 'Ambil Sendiri',
        'status' => 'Menunggu Pengirim',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'delivery_fee' => 15000,
        'tax_amount' => 12000,
        'total_price' => 127000,
        'shipping_address' => 'Jl. Kebagusan No. 5',
    ]);

    // Create ineligible order (Sedang Dikemas)
    $orderIneligible = Order::create([
        'id' => (string) Str::uuid(),
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'delivery_method' => 'Ambil Sendiri',
        'status' => 'Sedang Dikemas',
        'subtotal' => 50000,
        'discount_amount' => 0,
        'delivery_fee' => 10000,
        'tax_amount' => 6000,
        'total_price' => 66000,
        'shipping_address' => 'Jl. Kebagusan No. 5',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->getJson('/api/driver/jobs');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveCount(1);
    expect($data[0]['order_id'])->toBe($orderEligible->id);
    expect($data[0]['store_name'])->toBe('Udin Store');
    expect($data[0]['delivery_fee'])->toEqual(15000.0);
    expect($data[0]['driver_earning'])->toEqual(12000.0); // 80% of 15000
});

test('driver can take eligible job and it changes status and assigns driver', function () {
    $driver = createDriverUser('Driver Agus', 'agus');
    $buyer = createBuyerUser();
    [$seller, $store] = createSellerAndStore();

    $order = Order::create([
        'id' => (string) Str::uuid(),
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'delivery_method' => 'Ambil Sendiri',
        'status' => 'Menunggu Pengirim',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'delivery_fee' => 15000,
        'tax_amount' => 12000,
        'total_price' => 127000,
        'shipping_address' => 'Jl. Kebagusan No. 5',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token_Agus', // case insensitivity check or match
    ]);

    // Let's pass the correct token from the helper which is 'token_agus' (lowercase)
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->postJson("/api/driver/jobs/{$order->id}/take");

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['message'])->toContain('Pekerjaan berhasil diambil');
    expect($data['job']['status'])->toBe('Active');
    expect($data['job']['earned_amount'])->toEqual(12000.0);

    // Assert database state updated
    $order->refresh();
    expect($order->status)->toBe('Sedang Dikirim');
    expect($order->driver_id)->toBe($driver->id);

    // Verify timeline / history log was created
    $history = $order->statusHistory()->latest()->first();
    expect($history->status)->toBe('Sedang Dikirim');
    expect($history->changed_by_role)->toBe('Driver');
});

test('driver cannot take job with wrong status', function () {
    $driver = createDriverUser('Driver Agus', 'agus');
    $buyer = createBuyerUser();
    [$seller, $store] = createSellerAndStore();

    $order = Order::create([
        'id' => (string) Str::uuid(),
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'delivery_method' => 'Ambil Sendiri',
        'status' => 'Sedang Dikemas',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'delivery_fee' => 15000,
        'tax_amount' => 12000,
        'total_price' => 127000,
        'shipping_address' => 'Jl. Kebagusan No. 5',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->postJson("/api/driver/jobs/{$order->id}/take");

    $response->assertStatus(400);
    expect($response->json()['message'])->toContain('tidak tersedia untuk diambil');
});

test('driver cannot take job already taken', function () {
    $driver1 = createDriverUser('Driver Agus', 'agus');
    $driver2 = createDriverUser('Driver Siti', 'siti');
    $buyer = createBuyerUser();
    [$seller, $store] = createSellerAndStore();

    $order = Order::create([
        'id' => (string) Str::uuid(),
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'delivery_method' => 'Ambil Sendiri',
        'status' => 'Menunggu Pengirim',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'delivery_fee' => 15000,
        'tax_amount' => 12000,
        'total_price' => 127000,
        'shipping_address' => 'Jl. Kebagusan No. 5',
    ]);

    // Driver 1 takes the job
    $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->postJson("/api/driver/jobs/{$order->id}/take")->assertStatus(200);

    // Driver 2 tries to take the same job
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token_siti',
        'X-Active-Role' => 'Driver',
    ])->postJson("/api/driver/jobs/{$order->id}/take");

    // Order status is now 'Sedang Dikirim', so it should fail with 400 or 409
    $response->assertStatus(400);
});

test('driver can complete a taken job and earns money', function () {
    $driver = createDriverUser('Driver Agus', 'agus');
    $buyer = createBuyerUser();
    [$seller, $store] = createSellerAndStore();

    $order = Order::create([
        'id' => (string) Str::uuid(),
        'buyer_id' => $buyer->id,
        'store_id' => $store->id,
        'delivery_method' => 'Ambil Sendiri',
        'status' => 'Menunggu Pengirim',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'delivery_fee' => 15000,
        'tax_amount' => 12000,
        'total_price' => 127000,
        'shipping_address' => 'Jl. Kebagusan No. 5',
    ]);

    // Take job first
    $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->postJson("/api/driver/jobs/{$order->id}/take")->assertStatus(200);

    // Verify active job exists
    $earningsResponse = $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->getJson('/api/driver/earnings');
    $earningsResponse->assertStatus(200);
    expect($earningsResponse->json()['active_job'])->not->toBeNull();
    expect($earningsResponse->json()['total_earnings'])->toEqual(0.0);

    // Complete job
    $response = $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->postJson("/api/driver/jobs/{$order->id}/complete");

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['message'])->toContain('Pengiriman selesai');
    expect($data['job']['status'])->toBe('Completed');

    // Assert database state updated
    $order->refresh();
    expect($order->status)->toBe('Pesanan Selesai');

    // Assert earnings updated
    $earningsResponse2 = $this->withHeaders([
        'Authorization' => 'Bearer token_agus',
        'X-Active-Role' => 'Driver',
    ])->getJson('/api/driver/earnings');
    $earningsResponse2->assertStatus(200);
    expect($earningsResponse2->json()['active_job'])->toBeNull();
    expect($earningsResponse2->json()['total_earnings'])->toEqual(12000.0);
    expect($earningsResponse2->json()['completed_count'])->toBe(1);
});
