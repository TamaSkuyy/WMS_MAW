<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleDeliverySlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_can_be_linked_to_a_delivery_slot_and_date(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 2)->first();

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $cycle->delivery_date);
        $this->assertSame('2026-07-15', $cycle->delivery_date->toDateString());
        $this->assertSame(2, $cycle->deliverySlot->slot_number);
    }

    public function test_delivery_slot_and_date_are_nullable(): void
    {
        $cycle = Cycle::factory()->create();

        $this->assertNull($cycle->delivery_date);
        $this->assertNull($cycle->delivery_slot_id);
    }
}
