<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->integer('capacity')->nullable()->after('zone')->comment('Max capacity (units)');
        });
    }

    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
