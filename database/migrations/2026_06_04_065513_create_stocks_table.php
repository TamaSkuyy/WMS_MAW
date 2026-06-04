<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('rack_id')->constrained('racks')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'rack_id']);
            $table->index('product_id');
            $table->index('rack_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
