<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Backfill created_by / updated_by pada data yang sudah ada
 * dengan id user superadmin (default) sebagai nilai awal audit.
 *
 * - Hanya mengisi baris yang masih NULL; nilai yang sudah terisi
 *   (mis. dari trait AuditableBy) tidak akan ditimpa.
 * - Superadmin dicari via role 'superadmin'; jika tidak ada user
 *   dengan role tersebut, fallback ke user dengan id terkecil.
 * - Jika tabel tidak punya kolom audit, dilewati (aman untuk fresh install).
 */
return new class extends Migration
{
    private array $tables = [
        'suppliers',
        'supplier_addresses',
        'vehicle_models',
        'product_categories',
        'products',
        'racks',
        'cycles',
        'cycle_items',
        'stocks',
        'shoppings',
        'shopping_items',
        'job_positions',
        'work_locations',
        'departments',
        'delivery_slots',
        'supplier_delivery_schedules',
        'shopping_locations',
        'employees',
        'shifts',
    ];

    public function up(): void
    {
        // Superadmin dicari via role 'superadmin' (jika role/tabel role sudah ada);
        // fallback ke user dengan id terkecil agar aman di DB yang belum di-seed.
        $superadminId = null;

        if (Schema::hasTable('roles')) {
            $superadminId = Role::where('name', 'superadmin')->first()?->users()->value('id');
        }

        $superadminId = $superadminId ?? User::min('id');

        if (! $superadminId) {
            return; // belum ada user sama sekali — tidak ada yang bisa di-backfill
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'created_by') || ! Schema::hasColumn($table, 'updated_by')) {
                continue;
            }

            DB::table($table)->whereNull('created_by')->update(['created_by' => $superadminId]);
            DB::table($table)->whereNull('updated_by')->update(['updated_by' => $superadminId]);
        }
    }

    public function down(): void
    {
        // Data migration — tidak ada rollback yang aman tanpa menghapus data.
    }
};
