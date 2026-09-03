<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log koreksi data transaksi final (shopping sudah dikirim / cycle sudah
     * diterima) — dibuat hanya oleh user berpermission "correct ...".
     * Menyimpan snapshot before/after + delta stok per baris untuk audit.
     */
    public function up(): void
    {
        Schema::create('stock_corrections', function (Blueprint $table) {
            $table->id();
            $table->morphs('correctable'); // Shopping | Cycle
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->json('deltas'); // ringkasan dampak stok (per part/rak)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_corrections');
    }
};
