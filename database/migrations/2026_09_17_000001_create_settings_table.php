<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `settings` — penyimpanan key/value untuk pengaturan aplikasi yang bisa
 * diubah superadmin dari UI (mis. on/off fitur). Sengaja generik supaya
 * penambahan pengaturan baru tidak perlu migrasi lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
