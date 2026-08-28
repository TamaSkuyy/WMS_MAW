<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            // Barang cripple (part tidak lengkap) — default no (false).
            // Saat ship, is_cripple=true → status 'cripple', bukan 'shipped'.
            $table->boolean('is_cripple')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropColumn('is_cripple');
        });
    }
};
