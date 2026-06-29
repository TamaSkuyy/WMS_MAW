<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_items', function (Blueprint $table) {
            $table->foreignId('rack_id')
                ->nullable()
                ->after('received_quantity')
                ->constrained('racks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycle_items', function (Blueprint $table) {
            $table->dropForeign(['rack_id']);
            $table->dropColumn('rack_id');
        });
    }
};
