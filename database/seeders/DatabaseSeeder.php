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
            WmsRoleSeeder::class,         // WMS roles & permissions
            MenuSeeder::class,            // Sidebar navigation (truncate dulu)
            RoleAndMenuSeeder::class,     // Super Admin user + Setup menu
            SupplierSeeder::class,        // 10 sample suppliers
            ProductSeeder::class,         // 5 models, 5 categories, 20 products
            RackSeeder::class,            // 5 sample racks
        ]);
    }
}
