<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Initial admin account (§4 — accounts are created by an admin; this
        // one bootstraps the very first login). Change the password after
        // first sign-in.
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Store Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        // A demo cashier so the role-gated UI can be exercised end to end.
        User::updateOrCreate(
            ['username' => 'cashier'],
            [
                'name' => 'Demo Cashier',
                'password' => Hash::make('password'),
                'role' => UserRole::Cashier,
                'is_active' => true,
            ]
        );

        // Only seed the demo catalog when the products table is empty so
        // repeated `db:seed` runs don't stack duplicates or hit the barcode
        // unique index.
        if (\App\Models\Product::query()->doesntExist()) {
            $this->call(InventorySeeder::class);
        }
    }
}
