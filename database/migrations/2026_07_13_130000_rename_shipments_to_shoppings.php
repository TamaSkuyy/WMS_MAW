<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('shipments', 'shoppings');
        Schema::rename('shipment_items', 'shopping_items');

        Schema::table('shoppings', function (Blueprint $table) {
            $table->renameColumn('shipment_date', 'shopping_date');
        });

        Schema::table('shopping_items', function (Blueprint $table) {
            $table->renameColumn('shipment_id', 'shopping_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->renameColumn('shopping_id', 'shipment_id');
        });

        Schema::table('shoppings', function (Blueprint $table) {
            $table->renameColumn('shopping_date', 'shipment_date');
        });

        Schema::rename('shopping_items', 'shipment_items');
        Schema::rename('shoppings', 'shipments');
    }
};
