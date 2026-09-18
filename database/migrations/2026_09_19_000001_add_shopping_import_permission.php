<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permission import Shopping (`import shoppings`) — HANYA Leader ke atas.
     *
     * Menggantikan permission `create shoppings` untuk route import:
     *   - Import Barang (alur 2 langkah, langkah 2)
     *   - Import Gabungan (alur lama)
     *   - Import File Header (disiapkan, tombol UI disembunyikan)
     *
     * Operator tetap boleh `create shoppings` (input header manual & tambah
     * shopping) tapi tidak boleh mengakses fitur import.
     */
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'import shoppings',
            'guard_name' => 'web',
        ]);

        foreach (['superadmin', 'leader'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::where('name', 'import shoppings')->first();
        if (! $permission) {
            return;
        }

        foreach (['superadmin', 'leader'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }

        $permission->delete();
    }
};
