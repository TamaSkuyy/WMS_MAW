<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MenuSeeder::class,            // Sidebar navigation menus (MUST run first)
            RoleAndMenuSeeder::class,     // Roles, permissions, admin user
            SupplierSeeder::class,        // 10 sample suppliers
            ProductSeeder::class,         // 5 models, 5 categories, 20 products
            RackSeeder::class,            // 5 sample racks
        ]);
    }
}
