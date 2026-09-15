<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal kirim shopping perlu menyimpan JAM, bukan hanya tanggal:
 * - form Create/Edit jadi tanggal + jam,
 * - importer Shopping sudah mem-parsing jam dari "Modify Date" tetapi selama
 *   kolom bertipe DATE jamnya terpotong menjadi 00:00.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dateTime('shopping_date')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->date('shopping_date')->change();
        });
    }
};
