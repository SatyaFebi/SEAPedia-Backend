<?php

namespace Database\Seeders;

use App\Models\ApplicationReview;
use App\Models\BuyerAddress;
use App\Models\BuyerWallet;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Demo Accounts (password: password123):
     *  - admin / admin@seapedia.test   → Admin
     *  - budi  / budi@seapedia.test    → Buyer + Seller (has store "Warung Budi", wallet 500k)
     *  - agus  / agus@seapedia.test    → Buyer + Seller + Driver (has store "Toko Agus", wallet 300k)
     *  - siti  / siti@seapedia.test    → Buyer + Driver (wallet 200k)
     */
    public function run(): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. USERS
        // ──────────────────────────────────────────────────────────────

        // Budi (Buyer + Seller)
        $budi = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'email' => 'budi@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $budi->id, 'role' => 'Buyer']);
        UserRole::create(['user_id' => $budi->id, 'role' => 'Seller']);

        // Agus (Buyer + Seller + Driver)
        $agus = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Agus Setiawan',
            'username' => 'agus',
            'email' => 'agus@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $agus->id, 'role' => 'Buyer']);
        UserRole::create(['user_id' => $agus->id, 'role' => 'Seller']);
        UserRole::create(['user_id' => $agus->id, 'role' => 'Driver']);

        // Siti (Buyer + Driver)
        $siti = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Siti Rahma',
            'username' => 'siti',
            'email' => 'siti@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $siti->id, 'role' => 'Buyer']);
        UserRole::create(['user_id' => $siti->id, 'role' => 'Driver']);

        // Admin
        $admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $admin->id, 'role' => 'Admin']);

        // ──────────────────────────────────────────────────────────────
        // 2. WALLETS (Buyer-role users)
        // ──────────────────────────────────────────────────────────────

        $budiWallet = BuyerWallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $budi->id,
            'balance' => 500000,
        ]);
        WalletTransaction::create([
            'id' => (string) Str::uuid(),
            'wallet_id' => $budiWallet->id,
            'amount' => 500000,
            'type' => 'TOPUP',
            'description' => 'Top Up Awal (Seed)',
        ]);

        $agusWallet = BuyerWallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $agus->id,
            'balance' => 300000,
        ]);
        WalletTransaction::create([
            'id' => (string) Str::uuid(),
            'wallet_id' => $agusWallet->id,
            'amount' => 300000,
            'type' => 'TOPUP',
            'description' => 'Top Up Awal (Seed)',
        ]);

        $sitiWallet = BuyerWallet::create([
            'id' => (string) Str::uuid(),
            'user_id' => $siti->id,
            'balance' => 200000,
        ]);
        WalletTransaction::create([
            'id' => (string) Str::uuid(),
            'wallet_id' => $sitiWallet->id,
            'amount' => 200000,
            'type' => 'TOPUP',
            'description' => 'Top Up Awal (Seed)',
        ]);

        // ──────────────────────────────────────────────────────────────
        // 3. ADDRESSES (Buyer-role users)
        // ──────────────────────────────────────────────────────────────

        BuyerAddress::create([
            'id' => (string) Str::uuid(),
            'user_id' => $budi->id,
            'address_details' => 'Jl. Sudirman No. 10, Jakarta Pusat, DKI Jakarta',
            'is_main' => true,
        ]);

        BuyerAddress::create([
            'id' => (string) Str::uuid(),
            'user_id' => $agus->id,
            'address_details' => 'Jl. Malioboro No. 5, Yogyakarta',
            'is_main' => true,
        ]);

        BuyerAddress::create([
            'id' => (string) Str::uuid(),
            'user_id' => $siti->id,
            'address_details' => 'Jl. Raya Darmo No. 22, Surabaya, Jawa Timur',
            'is_main' => true,
        ]);

        // ──────────────────────────────────────────────────────────────
        // 4. STORES & PRODUCTS (Seller-role users)
        // ──────────────────────────────────────────────────────────────

        // Budi's Store
        $budiStore = Store::create([
            'id' => (string) Str::uuid(),
            'user_id' => $budi->id,
            'store_name' => 'Warung Budi',
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'store_id' => $budiStore->id,
            'name' => 'Nasi Gudeg Spesial',
            'description' => 'Nasi gudeg khas Yogyakarta dengan ayam kampung, telur, dan sambal krecek. Disajikan hangat.',
            'price' => 35000,
            'stock' => 50,
            'image' => 'https://images.unsplash.com/photo-1590301157890-4810ed352733?auto=format&fit=crop&q=80&w=400',
            'category' => 'Kuliner',
        ]);
        Product::create([
            'id' => (string) Str::uuid(),
            'store_id' => $budiStore->id,
            'name' => 'Bakso Mercon Pedas',
            'description' => 'Bakso daging sapi asli dengan kuah pedas level 5. Cocok untuk pencinta makanan pedas!',
            'price' => 25000,
            'stock' => 100,
            'image' => 'https://images.unsplash.com/photo-1569050467447-ce54b3bbc37d?auto=format&fit=crop&q=80&w=400',
            'category' => 'Kuliner',
        ]);
        Product::create([
            'id' => (string) Str::uuid(),
            'store_id' => $budiStore->id,
            'name' => 'Es Teh Manis Jumbo',
            'description' => 'Es teh manis segar ukuran jumbo 1 liter. Cocok menemani makan siang.',
            'price' => 12000,
            'stock' => 200,
            'image' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&q=80&w=400',
            'category' => 'Kuliner',
        ]);

        // Agus's Store
        $agusStore = Store::create([
            'id' => (string) Str::uuid(),
            'user_id' => $agus->id,
            'store_name' => 'Toko Agus Otomotif',
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'store_id' => $agusStore->id,
            'name' => 'Oli Mesin Shell Helix 1L',
            'description' => 'Oli mesin Shell Helix Ultra 5W-40 1 Liter. Cocok untuk mesin bensin modern.',
            'price' => 85000,
            'stock' => 30,
            'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&q=80&w=400',
            'category' => 'Otomotif',
        ]);
        Product::create([
            'id' => (string) Str::uuid(),
            'store_id' => $agusStore->id,
            'name' => 'Busi NGK Iridium',
            'description' => 'Busi NGK Iridium IX untuk performa mesin maksimal. Tahan lama dan hemat bahan bakar.',
            'price' => 55000,
            'stock' => 60,
            'image' => 'https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&q=80&w=400',
            'category' => 'Otomotif',
        ]);

        // ──────────────────────────────────────────────────────────────
        // 5. DISCOUNTS (Vouchers & Promos)
        // ──────────────────────────────────────────────────────────────

        // Voucher: 10% off, max 100 uses
        Discount::create([
            'id' => (string) Str::uuid(),
            'code' => 'SEAPEDIA10',
            'type' => 'VOUCHER',
            'amount_type' => 'PERCENTAGE',
            'value' => 10,
            'max_usage' => 100,
            'used_count' => 0,
            'expiry_date' => '2030-01-01 00:00:00',
        ]);

        // Voucher: Rp 50.000 off, max 5 uses
        Discount::create([
            'id' => (string) Str::uuid(),
            'code' => 'HEMAT50',
            'type' => 'VOUCHER',
            'amount_type' => 'FIXED',
            'value' => 50000,
            'max_usage' => 5,
            'used_count' => 0,
            'expiry_date' => '2030-01-01 00:00:00',
        ]);

        // Promo: 20% off, unlimited uses
        Discount::create([
            'id' => (string) Str::uuid(),
            'code' => 'PROMO20',
            'type' => 'PROMO',
            'amount_type' => 'PERCENTAGE',
            'value' => 20,
            'max_usage' => null,
            'used_count' => 0,
            'expiry_date' => '2030-01-01 00:00:00',
        ]);

        // Expired promo (for testing rejection)
        Discount::create([
            'id' => (string) Str::uuid(),
            'code' => 'EXPIRED10',
            'type' => 'PROMO',
            'amount_type' => 'PERCENTAGE',
            'value' => 10,
            'max_usage' => null,
            'used_count' => 0,
            'expiry_date' => '2020-01-01 00:00:00',
        ]);

        // ──────────────────────────────────────────────────────────────
        // 6. SAMPLE APPLICATION REVIEWS
        // ──────────────────────────────────────────────────────────────

        ApplicationReview::create([
            'id' => (string) Str::uuid(),
            'user_id' => $budi->id,
            'reviewer_name' => 'Budi Santoso',
            'rating' => 5,
            'comment' => 'SEAPedia sangat mudah digunakan! Proses checkout cepat dan pengiriman tepat waktu.',
        ]);
        ApplicationReview::create([
            'id' => (string) Str::uuid(),
            'user_id' => $siti->id,
            'reviewer_name' => 'Siti Rahma',
            'rating' => 4,
            'comment' => 'Aplikasi bagus dan lengkap. Sebagai driver, fitur job-nya sangat memudahkan saya.',
        ]);
        ApplicationReview::create([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'reviewer_name' => 'Pengunjung Setia',
            'rating' => 5,
            'comment' => 'Katalog produk lokal yang lengkap. Sangat mendukung UMKM Indonesia!',
        ]);
    }
}
