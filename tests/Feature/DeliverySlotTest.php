<?php

namespace Tests\Feature;

use App\Models\DeliverySlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverySlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_six_fixed_windows(): void
    {
        $slots = DeliverySlot::orderBy('slot_number')->get();

        $this->assertCount(6, $slots);
        $this->assertSame(1, $slots->first()->slot_number);
        $this->assertSame('07:30:00', $slots->first()->time_start);
        $this->assertSame('09:30:00', $slots->first()->time_end);
        $this->assertSame(6, $slots->last()->slot_number);
        $this->assertSame('19:30:00', $slots->last()->time_end);
    }

    public function test_slot_number_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DeliverySlot::create([
            'slot_number' => 1,
            'time_start' => '00:00:00',
            'time_end' => '01:00:00',
        ]);
    }

    public function test_current_for_time_matches_active_window(): void
    {
        $slot = DeliverySlot::currentForTime(\Carbon\Carbon::parse('2026-07-15 08:00:00'));

        $this->assertSame(1, $slot->slot_number);
    }

    public function test_current_for_time_clamps_before_first_window(): void
    {
        $slot = DeliverySlot::currentForTime(\Carbon\Carbon::parse('2026-07-15 05:00:00'));

        $this->assertSame(1, $slot->slot_number);
    }

    public function test_current_for_time_clamps_after_last_window(): void
    {
        $slot = DeliverySlot::currentForTime(\Carbon\Carbon::parse('2026-07-15 22:00:00'));

        $this->assertSame(6, $slot->slot_number);
    }
}
