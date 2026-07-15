<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('cycle_number');
            $table->foreignId('delivery_slot_id')->nullable()->after('delivery_date')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_slot_id');
            $table->dropColumn('delivery_date');
        });
    }
};
