<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permission "manage settings" (superadmin) + menu "Pengaturan" di bawah
     * Setup → halaman /settings berisi switch on/off fitur (config/features.php).
     */
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'manage settings', 'guard_name' => 'web']);

        $role = Role::where('name', 'superadmin')->first();
        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        if (DB::table('menus')->where('name', 'Pengaturan')->exists()) {
            return;
        }

        $setup = DB::table('menus')->where('name', 'Setup')->where('group', 'others')->first();

        if (! $setup) {
            $setupId = DB::table('menus')->insertGetId([
                'name' => 'Setup',
                'icon' => 'SettingsIcon',
                'path' => null,
                'parent_id' => null,
                'sort_order' => 10,
                'group' => 'others',
                'permission_name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $setupId = $setup->id;
        }

        DB::table('menus')->insert([
            'name' => 'Pengaturan',
            'icon' => 'SettingsIcon',
            'path' => '/settings',
            'parent_id' => $setupId,
            'sort_order' => 10,
            'group' => 'others',
            'permission_name' => 'manage settings',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('name', 'Pengaturan')->delete();

        $permission = Permission::where('name', 'manage settings')->first();
        if ($permission) {
            $role = Role::where('name', 'superadmin')->first();
            if ($role) {
                $role->revokePermissionTo($permission);
            }
            $permission->delete();
        }
    }
};
