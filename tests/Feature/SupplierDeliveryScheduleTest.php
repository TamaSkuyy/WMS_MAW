<?php

namespace Tests\Feature;

use App\Models\DeliverySlot;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierDeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_be_scheduled_into_multiple_slots(): void
    {
        $supplier = Supplier::factory()->create();
        $slotOne = DeliverySlot::where('slot_number', 1)->first();
        $slotThree = DeliverySlot::where('slot_number', 3)->first();

        $supplier->scheduledSlots()->sync([$slotOne->id, $slotThree->id]);

        $this->assertEqualsCanonicalizing(
            [1, 3],
            $supplier->scheduledSlots()->pluck('slot_number')->all()
        );
    }

    public function test_same_supplier_slot_pair_cannot_repeat(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first();

        \App\Models\SupplierDeliverySchedule::create([
            'supplier_id' => $supplier->id,
            'delivery_slot_id' => $slot->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\SupplierDeliverySchedule::create([
            'supplier_id' => $supplier->id,
            'delivery_slot_id' => $slot->id,
        ]);
    }

    public function test_slot_reports_its_scheduled_suppliers(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 2)->first();
        $supplier->scheduledSlots()->attach($slot->id);

        $this->assertTrue($slot->scheduledSuppliers->contains($supplier));
    }
}
