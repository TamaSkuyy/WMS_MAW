<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('menus')->where('name', 'Report Transaction')->exists()) {
            return;
        }

        $parentId = DB::table('menus')->insertGetId([
            'name' => 'Report Transaction',
            'icon' => 'PieChartIcon',
            'path' => null,
            'parent_id' => null,
            'sort_order' => 21,
            'group' => 'main',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menus')->insert([
            [
                'name' => 'Receiving Report',
                'icon' => 'TableIcon',
                'path' => '/reports/receiving',
                'parent_id' => $parentId,
                'sort_order' => 1,
                'group' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Shipment Report',
                'icon' => 'ListIcon',
                'path' => '/reports/shipment',
                'parent_id' => $parentId,
                'sort_order' => 2,
                'group' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $parent = DB::table('menus')->where('name', 'Report Transaction')->first();

        if (! $parent) {
            return;
        }

        DB::table('menus')->where('parent_id', $parent->id)->delete();
        DB::table('menus')->where('id', $parent->id)->delete();
    }
};
