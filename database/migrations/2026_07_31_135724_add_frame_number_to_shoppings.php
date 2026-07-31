<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->string('frame_number')->nullable()->after('notes')->comment('Barcode/scanned frame number');
        });
    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropColumn('frame_number');
        });
    }
};
