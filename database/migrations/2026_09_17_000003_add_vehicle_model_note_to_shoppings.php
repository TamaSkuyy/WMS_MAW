<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan Model Kendaraan + Suffix pada Shopping (OPSIONAL, diisi operator).
 *
 * Berbeda dari `products.vehicle_model_id` (data master produk), dua kolom ini
 * adalah keterangan bebas pada transaksi shopping: "shopping ini untuk model
 * apa". Boleh dikosongkan; tidak memengaruhi stok atau validasi lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->string('vehicle_model_label', 100)->nullable()->after('frame_number')
                ->comment('Catatan model kendaraan yang diinput operator (opsional, teks bebas)');
            $table->string('vehicle_suffix', 50)->nullable()->after('vehicle_model_label')
                ->comment('Catatan suffix/varian kendaraan yang diinput operator (opsional)');
        });
    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropColumn(['vehicle_model_label', 'vehicle_suffix']);
        });
    }
};
