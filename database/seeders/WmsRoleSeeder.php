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
        // Reset cached roles and permissions
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

            // Transactions
            'view cycles', 'create cycles', 'edit cycles', 'delete cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',

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

        // 1. Super Admin — semua permission
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Admin Gudang — semua kecuali manage users/roles/permissions
        $adminGudang = Role::firstOrCreate(['name' => 'Admin Gudang', 'guard_name' => 'web']);
        $adminGudang->givePermissionTo(Permission::whereNotIn('name', [
            'manage users', 'manage roles', 'manage permissions',
        ])->get());

        // 3. Kepala Gudang — view semua + approve cycles & shopping
        $kepalaGudang = Role::firstOrCreate(['name' => 'Kepala Gudang', 'guard_name' => 'web']);
        $kepalaGudang->givePermissionTo([
            'view dashboard',
            // View all master data
            'view suppliers', 'view products', 'view racks',
            'view vehicle models', 'view product categories',
            'view job positions', 'view work locations', 'view departments', 'view employees',
            // View & approve transactions
            'view cycles', 'create cycles', 'edit cycles', 'delete cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',
        ]);

        // 4. Staff Gudang — operasional dasar
        $staffGudang = Role::firstOrCreate(['name' => 'Staff Gudang', 'guard_name' => 'web']);
        $staffGudang->givePermissionTo([
            'view dashboard',
            // View master data
            'view suppliers', 'view products', 'view racks',
            'view vehicle models', 'view product categories',
            // Operasional
            'view cycles', 'create cycles', 'edit cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'ship shoppings',
        ]);
    }
}
