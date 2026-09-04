<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permission "view logs" (HANYA superadmin) + menu "Log Viewer" di bawah
     * Setup — akses ke /log-viewer (opcodesio/log-viewer) dibatasi di
     * config/log-viewer.php memakai permission ini.
     */
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view logs', 'guard_name' => 'web']);

        $role = Role::where('name', 'superadmin')->first();
        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        if (DB::table('menus')->where('name', 'Log Viewer')->exists()) {
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
            'name' => 'Log Viewer',
            'icon' => 'PageIcon',
            'path' => '/log-viewer',
            'parent_id' => $setupId,
            'sort_order' => 11,
            'group' => 'others',
            'permission_name' => 'view logs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('name', 'Log Viewer')->delete();

        $permission = Permission::where('name', 'view logs')->first();
        if ($permission) {
            $role = Role::where('name', 'superadmin')->first();
            if ($role) {
                $role->revokePermissionTo($permission);
            }
            $permission->delete();
        }
    }
};
