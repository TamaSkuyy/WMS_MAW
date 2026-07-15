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
        if (DB::table('menus')->where('name', 'Jadwal Slot Pengiriman')->exists()) {
            return;
        }

        $masterDataId = DB::table('menus')->where('name', 'Master Data')->value('id');

        // MenuSeeder.php normally creates "Master Data", but it never runs
        // automatically in production (deploy only runs `migrate --force`),
        // and a fresh test database won't have it either. Create it here,
        // guarded, so this migration works regardless of seeder state.
        if (! $masterDataId) {
            $masterDataId = DB::table('menus')->insertGetId([
                'name' => 'Master Data',
                'icon' => 'FolderIcon',
                'path' => null,
                'parent_id' => null,
                'sort_order' => 10,
                'group' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->insert([
            'name' => 'Jadwal Slot Pengiriman',
            'icon' => 'TimeIcon',
            'path' => '/delivery-slots',
            'parent_id' => $masterDataId,
            'sort_order' => 11,
            'group' => 'main',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('name', 'Jadwal Slot Pengiriman')->delete();
    }
};
