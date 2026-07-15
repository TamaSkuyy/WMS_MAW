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

    public function test_supplier_with_real_cycle_but_no_configured_schedule_still_appears(): void
    {
        Carbon::setTestNow('2026-07-15 20:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 3)->first();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        // Deliberately no scheduledSlots()->attach() — an ad-hoc receive
        // with no pre-configured recurring schedule.

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 10]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('done', $snapshot['suppliers'][0]['status']);
        $this->assertNotEmpty($snapshot['parts']);
        $this->assertSame($product->id, $snapshot['parts'][0]['id']);

        $cycleEntry = collect($snapshot['cycles'])->firstWhere('supplierId', $supplier->id);
        $this->assertNotNull($cycleEntry);
        $this->assertSame(3, $cycleEntry['cycleNumber']);
        $this->assertSame(10, $cycleEntry['planQty']);
        $this->assertSame(10, $cycleEntry['actualQty']);
    }

    public function test_two_cycles_in_the_same_slot_produce_receipts_with_distinct_ids(): void
    {
        Carbon::setTestNow('2026-07-15 20:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 3)->first();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        // Two separate real cycles land in the same slot on the same day —
        // this happens in practice (e.g. two QR quick-receive scans).
        $firstCycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $firstCycle->items()->create(['product_id' => $product->id, 'quantity' => 5, 'received_quantity' => 5]);

        $secondCycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $secondCycle->items()->create(['product_id' => $product->id, 'quantity' => 3, 'received_quantity' => 2]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));
        $receiptsForPart = collect($snapshot['receipts'])->where('partId', $product->id);

        $this->assertCount(2, $receiptsForPart);
        $this->assertCount(2, $receiptsForPart->pluck('id')->unique(), 'receipt ids must be unique');
    }

    public function test_part_received_under_a_different_cycle_supplier_shows_under_that_supplier(): void
    {
        Carbon::setTestNow('2026-07-15 20:00:00');
        $catalogSupplier = Supplier::factory()->create();
        $receivingSupplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 3)->first();
        // The product's own master-data supplier is $catalogSupplier, but it
        // was actually received today under $receivingSupplier's cycle —
        // receiving forms allow picking any product for any supplier.
        $product = Product::factory()->create(['supplier_id' => $catalogSupplier->id]);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $receivingSupplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 5, 'received_quantity' => 5]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $part = collect($snapshot['parts'])->firstWhere('id', $product->id);
        $this->assertNotNull($part);
        $this->assertSame($receivingSupplier->id, $part['supplierId']);
    }

    public function test_supplier_with_neither_schedule_nor_cycles_is_still_standby(): void
    {
        Supplier::factory()->create();

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('standby', $snapshot['suppliers'][0]['status']);
        $this->assertEmpty($snapshot['parts']);
    }

    public function test_snapshot_keys_match_frontend_shape(): void
    {
        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertEqualsCanonicalizing(['suppliers', 'slots', 'cycles', 'parts', 'receipts'], array_keys($snapshot));
    }
}
