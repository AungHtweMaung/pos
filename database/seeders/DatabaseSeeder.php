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

        // Extra pharmacy counter staff so shifts can rotate.
        foreach (['thida' => 'Ma Thida Win', 'kyawzin' => 'Ko Kyaw Zin'] as $username => $name) {
            User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Cashier,
                    'is_active' => true,
                ]
            );
        }

        // Only seed the pharmacy catalogue when the products table is empty so
        // repeated `db:seed` runs don't stack duplicates or hit the barcode
        // unique index.
        if (\App\Models\Product::query()->doesntExist()) {
            $this->call(PharmacyCatalogSeeder::class);
        }

        // 90 days of sales, voids, restocks and shifts so the Sales, Reports
        // and Shift-history screens have data. No-ops once sales exist.
        $this->call(DemoSalesSeeder::class);
    }
}
