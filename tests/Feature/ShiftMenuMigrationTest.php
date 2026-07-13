<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftMenuMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_menu_exists_under_master_data(): void
    {
        $masterData = DB::table('menus')->where('name', 'Master Data')->first();
        $shiftMenu = DB::table('menus')->where('name', 'Shift')->first();

        $this->assertNotNull($masterData);
        $this->assertNotNull($shiftMenu);
        $this->assertSame($masterData->id, $shiftMenu->parent_id);
        $this->assertSame('/shifts', $shiftMenu->path);
        $this->assertSame('CalenderIcon', $shiftMenu->icon);
    }

    public function test_shift_menu_migration_is_idempotent(): void
    {
        $countBefore = DB::table('menus')->where('name', 'Shift')->count();
        $this->assertSame(1, $countBefore);

        (require database_path('migrations/2026_07_13_120200_add_shift_menu.php'))->up();

        $countAfter = DB::table('menus')->where('name', 'Shift')->count();
        $this->assertSame(1, $countAfter);
    }
}
