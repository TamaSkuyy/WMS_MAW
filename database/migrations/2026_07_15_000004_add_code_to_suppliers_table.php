<?php

use App\Models\Supplier;
use App\Support\SupplierCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
        });

        $usedCodes = [];
        Supplier::whereNull('code')->orderBy('id')->get()->each(function (Supplier $supplier) use (&$usedCodes) {
            $code = SupplierCodeGenerator::generate($supplier->name, $usedCodes);
            $usedCodes[] = $code;
            $supplier->code = $code;
            $supplier->saveQuietly();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 10)->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['suppliers_code_unique']);
            $table->dropColumn('code');
        });
    }
};
