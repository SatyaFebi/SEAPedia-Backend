<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Budi (Buyer, Seller)
        $budi = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'email' => 'budi@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $budi->id, 'role' => 'Buyer']);
        UserRole::create(['user_id' => $budi->id, 'role' => 'Seller']);

        // 2. Seed Agus (Buyer, Seller, Driver)
        $agus = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Agus Setiawan',
            'username' => 'agus',
            'email' => 'agus@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $agus->id, 'role' => 'Buyer']);
        UserRole::create(['user_id' => $agus->id, 'role' => 'Seller']);
        UserRole::create(['user_id' => $agus->id, 'role' => 'Driver']);

        // 3. Seed Siti (Buyer, Driver)
        $siti = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Siti Rahma',
            'username' => 'siti',
            'email' => 'siti@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $siti->id, 'role' => 'Buyer']);
        UserRole::create(['user_id' => $siti->id, 'role' => 'Driver']);

        // 4. Seed Admin (Admin)
        $admin = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@seapedia.test',
            'password' => Hash::make('password123'),
        ]);
        UserRole::create(['user_id' => $admin->id, 'role' => 'Admin']);
    }
}
