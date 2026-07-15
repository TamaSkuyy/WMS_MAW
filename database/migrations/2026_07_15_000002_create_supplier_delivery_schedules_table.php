<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_slot_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'delivery_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_delivery_schedules');
    }
};
