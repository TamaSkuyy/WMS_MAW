<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Seed permission "stock opname" + pasang ke role superadmin/leader,
     * dan tambah menu "Stock Opname" di bawah Transactions.
     * (Deploy production hanya menjalankan migrate --force, bukan seeder —
     *  jadi permission/menu baru dimasukkan lewat migration.)
     */
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // ── Permission ──────────────────────────────────────
        $permission = Permission::firstOrCreate(['name' => 'stock opname', 'guard_name' => 'web']);

        foreach (['superadmin', 'leader'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        // ── Menu ────────────────────────────────────────────
        if (DB::table('menus')->where('name', 'Stock Opname')->exists()) {
            return;
        }

        $transactions = DB::table('menus')->where('name', 'Transactions')->first();

        // MenuSeeder tidak pernah jalan otomatis di production — buat parent
        // bila belum ada supaya migration ini tetap aman di DB baru.
        if (! $transactions) {
            $transactionsId = DB::table('menus')->insertGetId([
                'name' => 'Transactions',
                'icon' => 'RefreshIcon',
                'path' => null,
                'parent_id' => null,
                'sort_order' => 20,
                'group' => 'main',
                'permission_name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $transactionsId = $transactions->id;
        }

        DB::table('menus')->insert([
            'name' => 'Stock Opname',
            'icon' => 'TableIcon',
            'path' => '/stock-opname',
            'parent_id' => $transactionsId,
            'sort_order' => 4,
            'group' => 'main',
            'permission_name' => 'stock opname',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('name', 'Stock Opname')->delete();

        $permission = Permission::where('name', 'stock opname')->first();
        if ($permission) {
            foreach (['superadmin', 'leader'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $role->revokePermissionTo($permission);
                }
            }
            $permission->delete();
        }
    }
};
