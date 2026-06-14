<?php

namespace Database\Seeders;

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

        // Menus are handled by MenuSeeder
    }
}
