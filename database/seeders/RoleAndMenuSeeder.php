<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['name' => 'view menus']);
        Permission::create(['name' => 'manage menus']);
        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'manage users']);
        Permission::create(['name' => 'view dashboard']);

        // create roles and assign created permissions
        $roleAdmin = Role::create(['name' => 'Super Admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        // create an admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@maw.com'],
            [
                'name' => 'Super Admin MAW',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole($roleAdmin);

        // create basic menus
        // 1. Dashboard
        // Menu::create([
        //     'name' => 'Dashboard',
        //     'icon' => 'GridIcon',
        //     'path' => '/',
        //     'sort_order' => 1,
        //     'permission_name' => 'view dashboard',
        //     'group' => 'main',
        // ]);

        // 2. Setup (Others) -> User Management & Menu Management
        $setupMenu = Menu::create([
            'name' => 'Setup',
            'icon' => 'BoxCubeIcon',
            'sort_order' => 1,
            'group' => 'others',
        ]);

        // Menu::create([
        //     'name' => 'Menu Management',
        //     'path' => '/menus',
        //     'parent_id' => $setupMenu->id,
        //     'sort_order' => 1,
        //     'permission_name' => 'manage menus',
        //     'group' => 'others',
        // ]);

        // Menu::create([
        //     'name' => 'User Management',
        //     'path' => '/users',
        //     'parent_id' => $setupMenu->id,
        //     'sort_order' => 2,
        //     'permission_name' => 'manage users',
        //     'group' => 'others',
        // ]);
    }
}
