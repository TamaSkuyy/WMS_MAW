<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Shipment;
use App\Models\Stock;
use App\Models\User;
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
}
