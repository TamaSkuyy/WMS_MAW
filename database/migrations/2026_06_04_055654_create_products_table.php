<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('part_number', 50)->unique();
            $table->string('name');
            $table->foreignId('vehicle_model_id')
                  ->constrained('vehicle_models')
                  ->restrictOnDelete();
            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->restrictOnDelete();
            $table->foreignId('category_id')
                  ->constrained('product_categories')
                  ->restrictOnDelete();
            $table->string('unit', 20)->default('pcs');
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('part_number');
            $table->index('supplier_id');
            $table->index('category_id');
            $table->index('vehicle_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
