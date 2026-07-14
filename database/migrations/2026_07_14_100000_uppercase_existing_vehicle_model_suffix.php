<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vehicle_models')
            ->whereNotNull('suffix')
            ->update(['suffix' => DB::raw('UPPER(suffix)')]);
    }

    public function down(): void
    {
        // Uppercasing is not reversible (original casing is not recoverable).
    }
};
