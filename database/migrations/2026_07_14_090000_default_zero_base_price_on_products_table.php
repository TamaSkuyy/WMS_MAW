<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNull('base_price')->update(['base_price' => 0]);

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price', 12, 2)->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price', 12, 2)->default(null)->nullable(true)->change();
        });
    }
};
