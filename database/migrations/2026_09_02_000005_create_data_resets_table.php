<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log pemutihan data via halaman web (superadmin) — jejak audit: siapa,
     * dari mana (IP/UA), jumlah record per tabel sebelum dihapus, file backup.
     */
    public function up(): void
    {
        Schema::create('data_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('counts');           // tabel => jumlah sebelum dihapus
            $table->string('backup_file')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_resets');
    }
};
