<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate dulu supaya data bersih, tidak tumpang tindih
        // Disable foreign key checks karena parent_id self-reference
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Menu::truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');

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

        Menu::create([
            'name' => 'Jabatan',
            'icon' => 'BadgeIcon',
            'path' => '/job-positions',
            'parent_id' => $masterData->id,
            'sort_order' => 6,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Lokasi Kerja',
            'icon' => 'MapPinIcon',
            'path' => '/work-locations',
            'parent_id' => $masterData->id,
            'sort_order' => 7,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Departemen',
            'icon' => 'BuildingIcon',
            'path' => '/departments',
            'parent_id' => $masterData->id,
            'sort_order' => 8,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Karyawan',
            'icon' => 'UsersIcon',
            'path' => '/employees',
            'parent_id' => $masterData->id,
            'sort_order' => 9,
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
            'name' => 'Receiving',
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
            'name' => 'Shopping',
            'icon' => 'UploadIcon',
            'path' => '/shipments',
            'parent_id' => $transactions->id,
            'sort_order' => 3,
            'group' => 'main',
        ]);

        // === REPORT TRANSACTION (parent) ===
        $reportTransaction = Menu::create([
            'name' => 'Report Transaction',
            'icon' => 'PieChartIcon',
            'sort_order' => 21,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Receiving Report',
            'icon' => 'TableIcon',
            'path' => '/reports/receiving',
            'parent_id' => $reportTransaction->id,
            'sort_order' => 1,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Shipment Report',
            'icon' => 'ListIcon',
            'path' => '/reports/shipment',
            'parent_id' => $reportTransaction->id,
            'sort_order' => 2,
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
            'permission_name' => 'view menus',
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
