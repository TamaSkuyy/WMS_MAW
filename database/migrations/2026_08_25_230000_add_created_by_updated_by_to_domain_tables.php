<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel domain yang belum punya kolom audit created_by / updated_by.
     * (employees & shifts sudah punya; tabel framework/log dilewati.)
     */
    private array $tables = [
        'suppliers',
        'supplier_addresses',
        'vehicle_models',
        'product_categories',
        'products',
        'racks',
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
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            });
        }

        // cycles: konversi user_id (PIC/pembuat) menjadi created_by + tambah updated_by,
        // supaya penamaan kolom audit konsisten di semua tabel.
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->renameColumn('user_id', 'created_by');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
            $table->dropForeign(['created_by']);
            $table->renameColumn('created_by', 'user_id');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $table->dropColumn(['created_by', 'updated_by']);
            });
        }
    }
};
