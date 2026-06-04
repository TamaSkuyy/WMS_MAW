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
        Schema::create('supplier_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->cascadeOnDelete();
            $table->string('street');
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postal_code', 20);
            $table->string('country', 100)->default('Indonesia');
            $table->enum('address_type', ['primary', 'shipping', 'billing'])->default('primary');
            $table->timestamps();

            // Indexes
            $table->index('supplier_id');
            // Ensure only one address per type per supplier
            $table->unique(['supplier_id', 'address_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_addresses');
    }
};
