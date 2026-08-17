<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->foreignId('shipped_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable()->after('shipped_by');
        });
    }

    public function down(): void
    {
        Schema::table('shoppings', function (Blueprint $table) {
            $table->dropForeign(['shipped_by']);
            $table->dropColumn(['shipped_by', 'shipped_at']);
        });
    }
};
