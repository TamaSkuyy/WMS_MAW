<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Shipment;
use App\Models\Stock;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_shipments(): void
    {
        Shipment::factory(3)->create();

        $response = $this->actingAs($this->user)->get(route('shipments.index'));

        $response->assertStatus(200);
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('shipments.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_shipment(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shipment_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shipments.store'), $data);

        $this->assertDatabaseHas('shipments', [
            'partner_name' => 'PT Test Partner',
            'status' => 'draft',
        ]);
        $response->assertRedirect();
    }

    public function test_create_passes_vehicle_models_to_view(): void
    {
        \App\Models\VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->get(route('shipments.create'));

        $response->assertStatus(200);
        $response->assertSee('Fortuner');
    }

    public function test_show_displays_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        $response = $this->actingAs($this->user)->get(route('shipments.show', $shipment));

        $response->assertStatus(200);
    }

    public function test_ship_deducts_stock(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();

        // Seed stock
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $response = $this->actingAs($this->user)->post(route('shipments.ship', $shipment));

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'shipped']);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 12]);
        $response->assertRedirect();
    }

    public function test_ship_fails_on_insufficient_stock(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $response = $this->actingAs($this->user)->post(route('shipments.ship', $shipment));

        $response->assertRedirect();
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'draft']); // unchanged
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'quantity' => 3]); // unchanged
    }

    public function test_destroy_deletes_draft_only(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user)->delete(route('shipments.destroy', $shipment));

        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
    }

    public function test_store_validates_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('shipments.store'), []);

        $response->assertSessionHasErrors(['partner_name', 'shipment_date', 'items']);
    }

    public function test_create_passes_products_with_stocks_to_view(): void
    {
        $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
        $rack = Rack::factory()->create();
        $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 7]);

        $response = $this->actingAs($this->user)->get(route('shipments.create'));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
    }

    public function test_edit_passes_products_with_stocks_to_view(): void
    {
        $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
        $rack = Rack::factory()->create();
        $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);
        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('shipments.edit', $shipment));

        $response->assertStatus(200);
    }

    public function test_store_merges_duplicate_product_and_rack_items(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shipment_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shipments.store'), $data);

        $response->assertRedirect();
        $shipment = Shipment::first();
        $this->assertCount(1, $shipment->items);
        $this->assertDatabaseHas('shipment_items', [
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 8,
        ]);
    }

    public function test_store_keeps_same_product_different_rack_as_separate_items(): void
    {
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shipment_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rackA->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rackB->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shipments.store'), $data);

        $response->assertRedirect();
        $shipment = Shipment::first();
        $this->assertCount(2, $shipment->items);
    }

    public function test_ship_second_call_is_rejected_and_stock_not_double_deducted(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $first = $this->actingAs($this->user)->post(route('shipments.ship', $shipment));
        $first->assertRedirect();

        $second = $this->actingAs($this->user)->post(route('shipments.ship', $shipment));
        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 12,
        ]);
    }
}
