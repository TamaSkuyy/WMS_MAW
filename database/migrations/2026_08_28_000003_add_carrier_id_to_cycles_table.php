<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PIC yang membawa part untuk cycle ini.
        // Catatan: kolom lama `user_id` di cycles sudah di-rename menjadi `created_by`
        // oleh 2026_08_25_230000 (PIC/pembuat), jadi PIC pembawa pakai kolom baru.
        Schema::table('cycles', function (Blueprint $table) {
            $table->foreignId('carrier_id')->nullable()->after('supplier_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropForeign(['carrier_id']);
            $table->dropColumn('carrier_id');
        });
    }
};
