<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Perluas ENUM status: tambah 'cripple' (barang dikirim tapi part tidak lengkap).
        // Kolom status asli dibuat sebagai ENUM('draft','shipped','completed') di
        // 2026_06_04_100000_create_shipments_table.
        DB::statement("ALTER TABLE `shoppings` MODIFY `status` ENUM('draft','shipped','cripple','completed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `shoppings` MODIFY `status` ENUM('draft','shipped','completed') NOT NULL DEFAULT 'draft'");
    }
};
