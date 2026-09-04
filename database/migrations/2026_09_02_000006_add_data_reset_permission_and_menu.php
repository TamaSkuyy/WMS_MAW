<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permission "reset data" (HANYA superadmin) + menu "Pemutihan Data"
     * di bawah Setup (group others) — fitur menghapus data transaksi via web.
     */
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'reset data', 'guard_name' => 'web']);

        $role = Role::where('name', 'superadmin')->first();
        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        if (DB::table('menus')->where('name', 'Pemutihan Data')->exists()) {
            return;
        }

        $setup = DB::table('menus')->where('name', 'Setup')->where('group', 'others')->first();

        // MenuSeeder tidak jalan otomatis di production — buat parent bila belum ada.
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
            'name' => 'Pemutihan Data',
            'icon' => 'TableIcon',
            'path' => '/data-reset',
            'parent_id' => $setupId,
            'sort_order' => 10,
            'group' => 'others',
            'permission_name' => 'reset data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('name', 'Pemutihan Data')->delete();

        $permission = Permission::where('name', 'reset data')->first();
        if ($permission) {
            $role = Role::where('name', 'superadmin')->first();
            if ($role) {
                $role->revokePermissionTo($permission);
            }
            $permission->delete();
        }
    }
};
