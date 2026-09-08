<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor cycle berubah dari "nomor urut global per supplier (terus bertambah)"
 * menjadi "nomor gelombang dalam satu hari per supplier (reset tiap hari)".
 *
 * Sebelum: unik (supplier_id, cycle_number)  → angka tidak boleh dipakai ulang.
 * Sesudah: unik (supplier_id, delivery_date, cycle_number) → supplier boleh
 * punya cycle #1..#N setiap hari (tanggal berbeda = nomor boleh diulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropUnique('cycles_supplier_id_cycle_number_unique');
            $table->unique(
                ['supplier_id', 'delivery_date', 'cycle_number'],
                'cycles_supplier_date_cycle_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropUnique('cycles_supplier_date_cycle_number_unique');
            $table->unique(['supplier_id', 'cycle_number']);
        });
    }
};
