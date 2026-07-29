<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropColumn('partner_name');
            $table->foreignId('shopping_location_id')->nullable()->after('id')->constrained('shopping_locations')->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropForeign(['shopping_location_id']);
            $table->dropColumn('shopping_location_id');
            $table->string('partner_name')->nullable()->after('id');
        });
    }
};
