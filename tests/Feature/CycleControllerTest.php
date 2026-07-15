<?php

namespace Tests\Feature;

use App\Events\StockChanged;
use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CycleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_cycles(): void
    {
        Cycle::factory(3)->create();
        $response = $this->actingAs($this->user)->get(route('cycles.index'));
        $response->assertStatus(200);
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('cycles.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_cycle_with_items(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'cycle_number' => 1,
            'notes' => 'Test cycle',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('cycles.store'), $data);

        $this->assertDatabaseHas('cycles', ['cycle_number' => 1, 'status' => 'draft']);
        $this->assertDatabaseHas('cycle_items', ['product_id' => $product->id, 'quantity' => 10]);
        $response->assertRedirect();
    }

    public function test_show_displays_cycle(): void
    {
        $cycle = Cycle::factory()->create();
        $response = $this->actingAs($this->user)->get(route('cycles.show', $cycle));
        $response->assertStatus(200);
    }

    public function test_edit_shows_form_for_draft(): void
    {
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $response = $this->actingAs($this->user)->get(route('cycles.edit', $cycle));
        $response->assertStatus(200);
    }

    public function test_update_modifies_draft_cycle(): void
    {
        $cycle = Cycle::factory()->create(['status' => 'draft', 'cycle_number' => 3]);
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $cycle->supplier_id,
            'notes' => 'Updated notes',
            'items' => [['product_id' => $product->id, 'quantity' => 20]],
        ];

        $response = $this->actingAs($this->user)->put(route('cycles.update', $cycle), $data);
        // cycle_number is server-assigned and never editable, so it stays unchanged.
        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'cycle_number' => 3, 'notes' => 'Updated notes']);
        $response->assertRedirect();
    }

    public function test_destroy_deletes_cycle(): void
    {
        $cycle = Cycle::factory()->create();
        $response = $this->actingAs($this->user)->delete(route('cycles.destroy', $cycle));
        $this->assertDatabaseMissing('cycles', ['id' => $cycle->id]);
        $response->assertRedirect();
    }

    public function test_receive_completes_cycle_and_updates_stock(): void
    {
        $rack = Rack::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        $data = [
            'items' => [
                [
                    'id' => $item->id,
                    'received_quantity' => 8,
                    'rack_id' => $rack->id,
                    'notes' => '2 pcs damaged',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('cycles.receive', $cycle), $data);

        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'status' => 'completed']);
        $this->assertDatabaseHas('cycle_items', ['id' => $item->id, 'received_quantity' => 8]);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);
        $response->assertRedirect();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('cycles.store'), []);
        $response->assertSessionHasErrors(['supplier_id', 'items']);
    }

    public function test_receive_saves_rack_id_to_cycle_item(): void
    {
        $rack = Rack::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        $this->actingAs($this->user)->post(route('cycles.receive', $cycle), [
            'items' => [[
                'id' => $item->id,
                'received_quantity' => 5,
                'rack_id' => $rack->id,
                'notes' => null,
            ]],
        ]);

        $this->assertDatabaseHas('cycle_items', [
            'id' => $item->id,
            'rack_id' => $rack->id,
        ]);
    }

    public function test_show_passes_last_used_racks(): void
    {
        $rack = Rack::factory()->create(['code' => 'H-01']);
        $product = Product::factory()->create();

        $priorCycle = Cycle::factory()->create(['status' => 'completed']);
        CycleItem::factory()->create([
            'cycle_id' => $priorCycle->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'received_quantity' => 5,
        ]);

        $cycle = Cycle::factory()->create(['status' => 'draft']);
        CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->user)->get(route('cycles.show', $cycle));

        $response->assertStatus(200);
    }

    public function test_quick_receive_form_returns_200(): void
    {
        $response = $this->actingAs($this->user)->get(route('cycles.quick-receive.form'));
        $response->assertStatus(200);
    }

    public function test_quick_receive_creates_cycle_and_updates_stock(): void
    {
        $supplier = Supplier::factory()->create();
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ]);

        $this->assertDatabaseHas('cycles', [
            'supplier_id' => $supplier->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('cycle_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'received_quantity' => 3,
            'rack_id' => $rack->id,
        ]);
        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 3,
        ]);
        $response->assertRedirect();
    }

    public function test_quick_receive_validates_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), []);
        $response->assertSessionHasErrors(['supplier_id', 'items']);
    }

    public function test_store_auto_generates_sequential_cycle_number(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ];

        $this->actingAs($this->user)->post(route('cycles.store'), $data);
        $this->actingAs($this->user)->post(route('cycles.store'), $data);

        $cycleNumbers = Cycle::where('supplier_id', $supplier->id)->orderBy('cycle_number')->pluck('cycle_number')->all();
        $this->assertSame([1, 2], $cycleNumbers);
    }

    public function test_store_ignores_client_supplied_cycle_number(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'cycle_number' => 999,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ];

        $this->actingAs($this->user)->post(route('cycles.store'), $data);

        $this->assertDatabaseHas('cycles', ['supplier_id' => $supplier->id, 'cycle_number' => 1]);
        $this->assertDatabaseMissing('cycles', ['supplier_id' => $supplier->id, 'cycle_number' => 999]);
    }

    public function test_store_merges_duplicate_product_items(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('cycles.store'), $data);

        $response->assertRedirect();
        $cycle = Cycle::first();
        $this->assertCount(1, $cycle->items);
        $this->assertDatabaseHas('cycle_items', [
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);
    }

    public function test_update_merges_duplicate_product_items(): void
    {
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $cycle->supplier_id,
            'cycle_number' => $cycle->cycle_number,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
                ['product_id' => $product->id, 'quantity' => 6],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('cycles.update', $cycle), $data);

        $response->assertRedirect();
        $this->assertCount(1, $cycle->fresh()->items);
        $this->assertDatabaseHas('cycle_items', [
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
    }

    public function test_receive_second_call_is_rejected_and_stock_not_doubled(): void
    {
        $rack = Rack::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        $payload = [
            'items' => [[
                'id' => $item->id,
                'received_quantity' => 10,
                'rack_id' => $rack->id,
                'notes' => null,
            ]],
        ];

        $first = $this->actingAs($this->user)->post(route('cycles.receive', $cycle), $payload);
        $first->assertRedirect();

        $second = $this->actingAs($this->user)->post(route('cycles.receive', $cycle), $payload);
        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 10,
        ]);
    }

    public function test_receive_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        $this->actingAs($this->user)->post(route('cycles.receive', $cycle), [
            'items' => [[
                'id' => $item->id,
                'received_quantity' => 10,
                'rack_id' => $rack->id,
                'notes' => null,
            ]],
        ]);

        Event::assertDispatched(StockChanged::class, fn (StockChanged $event) => $event->supplierId === $cycle->supplier_id);
    }

    public function test_receive_does_not_dispatch_stock_changed_event_when_rejected(): void
    {
        Event::fake([StockChanged::class]);

        $cycle = Cycle::factory()->create(['status' => 'completed']);

        $this->actingAs($this->user)->post(route('cycles.receive', $cycle), [
            'items' => [],
        ]);

        Event::assertNotDispatched(StockChanged::class);
    }

    public function test_quick_receive_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $supplier = Supplier::factory()->create();
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ]);

        Event::assertDispatched(StockChanged::class, fn (StockChanged $event) => $event->supplierId === $supplier->id);
    }

    public function test_store_sets_delivery_date_and_slot_from_current_time(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15 08:00:00');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->post(route('cycles.store'), [
            'supplier_id' => $supplier->id,
            'cycle_number' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ]);

        $response->assertRedirect();
        $cycle = Cycle::first();
        $this->assertSame('2026-07-15', $cycle->delivery_date->toDateString());
        $this->assertSame(1, $cycle->deliverySlot->slot_number);

        \Carbon\Carbon::setTestNow();
    }

    public function test_quick_receive_store_sets_delivery_date_and_slot(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15 18:00:00');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $response = $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]],
        ]);

        $response->assertRedirect();
        $cycle = Cycle::first();
        $this->assertSame('2026-07-15', $cycle->delivery_date->toDateString());
        $this->assertSame(6, $cycle->deliverySlot->slot_number);

        \Carbon\Carbon::setTestNow();
    }
}
