<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_real_data(): void
    {
        Supplier::factory()->create();

        $response = $this->get(route('delivery-monitor'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('DeliveryMonitor/Index')
            ->has('suppliers', 1)
            ->has('slots', 6)
            ->has('selectedDate')
        );
    }

    public function test_index_accepts_a_date_query_param(): void
    {
        $response = $this->get(route('delivery-monitor', ['date' => '2026-01-01']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('selectedDate', '2026-01-01'));
    }

    public function test_ledger_returns_supplier_cycle_history_newest_first(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 2)->first();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $older = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-01-01',
            'delivery_slot_id' => $slot->id,
        ]);
        $older->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 8]);

        $newer = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-02-01',
            'delivery_slot_id' => $slot->id,
        ]);
        $newer->items()->create(['product_id' => $product->id, 'quantity' => 20, 'received_quantity' => 20]);

        $response = $this->getJson(route('delivery-monitor.ledger', $supplier));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($newer->id, $data[0]['id']);
        $this->assertSame('2026-02-01', $data[0]['deliveryDate']);
        $this->assertSame(2, $data[0]['slotNumber']);
        $this->assertSame(20, $data[0]['planQty']);
        $this->assertSame(20, $data[0]['actualQty']);
        $this->assertSame($older->id, $data[1]['id']);
    }

    public function test_index_falls_back_to_today_when_date_is_garbage(): void
    {
        Supplier::factory()->create();

        // Sending an unparseable date string should NOT 500 — it should
        // silently fall back to today's date.
        $response = $this->get(route('delivery-monitor', ['date' => 'not-a-date-at-all']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('DeliveryMonitor/Index')
            ->where('selectedDate', now()->toDateString())
        );
    }

    public function test_ledger_only_includes_cycles_for_the_given_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $otherSupplier = Supplier::factory()->create();
        Cycle::factory()->create(['supplier_id' => $otherSupplier->id]);

        $response = $this->getJson(route('delivery-monitor.ledger', $supplier));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
