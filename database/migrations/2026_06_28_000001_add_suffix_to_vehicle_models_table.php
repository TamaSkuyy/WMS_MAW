<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->string('suffix', 50)->nullable()->after('name');
            $table->dropUnique(['name', 'brand']);
            $table->unique(['name', 'brand', 'suffix']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->dropUnique(['name', 'brand', 'suffix']);
            $table->dropColumn('suffix');
            $table->unique(['name', 'brand']);
        });
    }
};
