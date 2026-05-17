<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create permission for managing roles
        $permission = Permission::firstOrCreate(['name' => 'manage roles']);

        // 2. Assign permission to Super Admin
        $roleAdmin = Role::where('name', 'Super Admin')->first();
        if ($roleAdmin) {
            $roleAdmin->givePermissionTo($permission);
        }

        // 3. Add to Setup menu
        $setupMenu = Menu::where('name', 'Setup')->first();
        
        if ($setupMenu) {
            Menu::firstOrCreate(
                ['name' => 'Role Management'],
                [
                    'path' => '/roles',
                    'parent_id' => $setupMenu->id,
                    'sort_order' => 3,
                    'permission_name' => 'manage roles',
                    'group' => 'others',
                ]
            );
        }
    }
}
