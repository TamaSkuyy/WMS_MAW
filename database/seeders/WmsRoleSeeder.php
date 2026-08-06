<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class WmsRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── PERMISSIONS ────────────────────────────────────
        $permissions = [
            // Dashboard
            'view dashboard',

            // Master Data
            'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers',
            'view products', 'create products', 'edit products', 'delete products',
            'view racks', 'create racks', 'edit racks', 'delete racks',
            'view vehicle models', 'create vehicle models', 'edit vehicle models', 'delete vehicle models',
            'view product categories', 'create product categories', 'edit product categories', 'delete product categories',
            'view job positions', 'create job positions', 'edit job positions', 'delete job positions',
            'view work locations', 'create work locations', 'edit work locations', 'delete work locations',
            'view departments', 'create departments', 'edit departments', 'delete departments',
            'view employees', 'create employees', 'edit employees', 'delete employees',
            'view shifts', 'create shifts', 'edit shifts', 'delete shifts',
            'view delivery slots', 'edit delivery slots',
            'view shopping locations', 'create shopping locations', 'edit shopping locations', 'delete shopping locations',

            // Transactions
            'view cycles', 'create cycles', 'edit cycles', 'delete cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',

            // Import / Export
            'import cycles', 'export cycles',

            // Reports
            'view receiving report', 'export receiving report',
            'view shopping report', 'export shopping report',
            'view supplier performance',

            // Delivery Monitor
            'view delivery monitor',

            // System
            'view users', 'manage users',
            'view roles', 'manage roles',
            'view permissions', 'manage permissions',
            'view menus', 'manage menus',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ─── ROLES ──────────────────────────────────────────

        // 1. Superadmin — semua permission
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin->syncPermissions(Permission::all());

        // 2. Leader — view all master data + operasional CRUD + reports
        $leader = Role::firstOrCreate(['name' => 'leader', 'guard_name' => 'web']);
        $leader->syncPermissions([
            'view dashboard',

            // View all master data + create/edit (no delete)
            'view suppliers', 'view products', 'view racks',
            'view vehicle models', 'view product categories',
            'view job positions', 'view work locations', 'view departments', 'view employees',
            'view shifts', 'view delivery slots',
            'view shopping locations',

            // Transactions (full CRUD + ship/receive)
            'view cycles', 'create cycles', 'edit cycles', 'delete cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',

            // Import / Export
            'import cycles', 'export cycles',

            // Reports
            'view receiving report', 'export receiving report',
            'view shopping report', 'export shopping report',
            'view supplier performance',

            // Delivery Monitor
            'view delivery monitor',
        ]);

        // 3. Operator — view + create only, no edit/delete/approve
        $operator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operator->syncPermissions([
            'view dashboard',

            // View master data (read-only)
            'view suppliers', 'view products', 'view racks',
            'view vehicle models', 'view product categories',
            'view shopping locations', 'view shifts',

            // Delivery Monitor
            'view delivery monitor',

            // Transactions (create + view only)
            'view cycles', 'create cycles',
            'view stocks',
            'view shoppings', 'create shoppings',
        ]);
    }
}
