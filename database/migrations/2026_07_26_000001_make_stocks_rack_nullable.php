<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow stock records without a rack — used for "relay/overflow"
        // items that were received but not yet assigned to a real rack.
        // The unique index on (product_id, rack_id) still works because
        // MySQL treats NULLs as distinct values in unique indexes.
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('rack_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('rack_id')->nullable(false)->change();
        });
    }
};
