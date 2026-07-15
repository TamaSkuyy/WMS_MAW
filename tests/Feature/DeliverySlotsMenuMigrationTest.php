<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliverySlotsMenuMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_slots_menu_exists_under_master_data(): void
    {
        $masterData = DB::table('menus')->where('name', 'Master Data')->first();
        $menu = DB::table('menus')->where('name', 'Jadwal Slot Pengiriman')->first();

        $this->assertNotNull($masterData);
        $this->assertNotNull($menu);
        $this->assertSame($masterData->id, $menu->parent_id);
        $this->assertSame('/delivery-slots', $menu->path);
        $this->assertSame('TimeIcon', $menu->icon);
    }

    public function test_delivery_slots_menu_migration_is_idempotent(): void
    {
        $countBefore = DB::table('menus')->where('name', 'Jadwal Slot Pengiriman')->count();
        $this->assertSame(1, $countBefore);

        (require database_path('migrations/2026_07_15_000005_add_delivery_slots_menu.php'))->up();

        $countAfter = DB::table('menus')->where('name', 'Jadwal Slot Pengiriman')->count();
        $this->assertSame(1, $countAfter);
    }
}
