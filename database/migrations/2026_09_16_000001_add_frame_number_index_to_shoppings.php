<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index frame_number pada shoppings.
 *
 * Dua jalur memakai kolom ini dan sebelumnya melakukan full table scan:
 *  - import barang: lookup frame per baris file (ribuan kali per import);
 *  - modal Kirim Massal: pencarian/scan frame draft.
 *
 * Non-unik karena satu frame number memang boleh muncul lebih dari sekali
 * (pesanan ulang setelah frame dikirim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->index('frame_number', 'shoppings_frame_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropIndex('shoppings_frame_number_index');
        });
    }
};
