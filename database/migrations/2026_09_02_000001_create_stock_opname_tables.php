<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->date('opname_date');
            // Informasi cakupan saat template diunduh (opsional)
            $table->string('zone')->nullable();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('diff_items')->default(0);
            $table->unsignedInteger('new_items')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot untuk riwayat stabil walau master data berubah
            $table->string('part_number');
            $table->string('product_name');
            $table->string('rack_code')->nullable();
            $table->integer('sap_qty');      // qty sistem sebelum opname
            $table->integer('actual_qty');   // hasil hitung fisik
            $table->integer('diff');         // actual - sap
            $table->timestamps();

            $table->index('stock_opname_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
