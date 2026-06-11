<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if menus already exist
        if (Menu::count() > 0) {
            return;
        }

        // === MAIN group ===
        // Dashboard
        Menu::create([
            'name' => 'Dashboard',
            'icon' => 'GridIcon',
            'path' => '/dashboard',
            'sort_order' => 1,
            'group' => 'main',
        ]);

        // === MASTER DATA (parent) ===
        $masterData = Menu::create([
            'name' => 'Master Data',
            'icon' => 'FolderIcon',
            'sort_order' => 10,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Suppliers',
            'icon' => 'BoxIcon',
            'path' => '/suppliers',
            'parent_id' => $masterData->id,
            'sort_order' => 1,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Products',
            'icon' => 'TagIcon',
            'path' => '/products',
            'parent_id' => $masterData->id,
            'sort_order' => 2,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Racks',
            'icon' => 'PinIcon',
            'path' => '/racks',
            'parent_id' => $masterData->id,
            'sort_order' => 3,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Model Kendaraan',
            'icon' => 'CarIcon',
            'path' => '/vehicle-models',
            'parent_id' => $masterData->id,
            'sort_order' => 4,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Kategori Produk',
            'icon' => 'CategoryIcon',
            'path' => '/product-categories',
            'parent_id' => $masterData->id,
            'sort_order' => 5,
            'group' => 'main',
        ]);

        // === TRANSACTIONS (parent) ===
        $transactions = Menu::create([
            'name' => 'Transactions',
            'icon' => 'RefreshIcon',
            'sort_order' => 20,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Cycles',
            'icon' => 'DownloadIcon',
            'path' => '/cycles',
            'parent_id' => $transactions->id,
            'sort_order' => 1,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Stocks',
            'icon' => 'LayersIcon',
            'path' => '/stocks',
            'parent_id' => $transactions->id,
            'sort_order' => 2,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Shipments',
            'icon' => 'UploadIcon',
            'path' => '/shipments',
            'parent_id' => $transactions->id,
            'sort_order' => 3,
            'group' => 'main',
        ]);

        // === OTHERS group ===
        // Setup is the parent menu for menu management, user management, role management, and permission management
        $setupMenu = Menu::create([
            'name' => 'Setup',
            'icon' => 'SettingsIcon',
            'sort_order' => 10,
            'group' => 'others',
        ]);

        Menu::create([
            'name' => 'Menu Management',
            'icon' => 'MenuIcon',
            'path' => '/menus',
            'parent_id' => $setupMenu->id,
            'sort_order' => 1,
            'group' => 'others',
        ]);

        Menu::create([
            'name' => 'Users',
            'icon' => 'UserIcon',
            'path' => '/users',
            'parent_id' => $setupMenu->id,
            'sort_order' => 1,
            'group' => 'others',
        ]);

        Menu::create([
            'name' => 'Roles',
            'icon' => 'ShieldIcon',
            'path' => '/roles',
            'parent_id' => $setupMenu->id,
            'sort_order' => 2,
            'group' => 'others',
        ]);

        Menu::create([
            'name' => 'Permissions',
            'icon' => 'KeyIcon',
            'path' => '/permissions',
            'parent_id' => $setupMenu->id,
            'sort_order' => 3,
            'group' => 'others',
        ]);
    }
}
