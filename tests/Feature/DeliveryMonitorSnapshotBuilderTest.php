<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryMonitorSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_supplier_with_no_schedule_is_standby(): void
    {
        Supplier::factory()->create();

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('standby', $snapshot['suppliers'][0]['status']);
    }

    public function test_supplier_with_fully_received_slot_is_done(): void
    {
        Carbon::setTestNow('2026-07-15 20:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 10]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('done', $snapshot['suppliers'][0]['status']);
    }

    public function test_supplier_with_overdue_incomplete_slot_is_alert(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first(); // ends 09:30, already passed
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 4]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('alert', $snapshot['suppliers'][0]['status']);
    }

    public function test_supplier_with_not_yet_overdue_incomplete_slot_is_live(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 6)->first(); // ends 19:30, not passed yet
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 0]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('live', $snapshot['suppliers'][0]['status']);
    }

    public function test_receipt_status_is_computed_per_part(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first();
        $matched = Product::factory()->create(['supplier_id' => $supplier->id]);
        $shortage = Product::factory()->create(['supplier_id' => $supplier->id]);
        $over = Product::factory()->create(['supplier_id' => $supplier->id]);
        $pending = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $matched->id, 'quantity' => 10, 'received_quantity' => 10]);
        $cycle->items()->create(['product_id' => $shortage->id, 'quantity' => 10, 'received_quantity' => 4]);
        $cycle->items()->create(['product_id' => $over->id, 'quantity' => 10, 'received_quantity' => 15]);
        $cycle->items()->create(['product_id' => $pending->id, 'quantity' => 10, 'received_quantity' => 0]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));
        $statusByPartId = collect($snapshot['receipts'])->pluck('status', 'partId');

        $this->assertSame('matched', $statusByPartId[$matched->id]);
        $this->assertSame('shortage', $statusByPartId[$shortage->id]);
        $this->assertSame('over', $statusByPartId[$over->id]);
        $this->assertSame('pending', $statusByPartId[$pending->id]);
    }

    public function test_snapshot_keys_match_frontend_shape(): void
    {
        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertEqualsCanonicalizing(['suppliers', 'slots', 'cycles', 'parts', 'receipts'], array_keys($snapshot));
    }
}
