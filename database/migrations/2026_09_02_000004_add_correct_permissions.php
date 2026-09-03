<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permission koreksi data final — HANYA untuk superadmin (user tertinggi).
     * Nama permission netral (bukan per-role) supaya mudah di-assign ke role
     * lain di kemudian hari tanpa migration baru.
     */
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['correct shoppings', 'correct cycles'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            // Hanya superadmin
            $role = Role::where('name', 'superadmin')->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['correct shoppings', 'correct cycles'] as $name) {
            $permission = Permission::where('name', $name)->first();
            if ($permission) {
                $role = Role::where('name', 'superadmin')->first();
                if ($role) {
                    $role->revokePermissionTo($permission);
                }
                $permission->delete();
            }
        }
    }
};
