<?php

namespace Tests\Feature;

use App\Events\StockChanged;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\Stock;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ShoppingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_shoppings(): void
    {
        Shopping::factory(3)->create();

        $response = $this->actingAs($this->user)->get(route('shoppings.index'));

        $response->assertStatus(200);
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('shoppings.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_shopping(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shopping_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shoppings.store'), $data);

        $this->assertDatabaseHas('shoppings', [
            'partner_name' => 'PT Test Partner',
            'status' => 'draft',
        ]);
        $response->assertRedirect();
    }

    public function test_create_passes_vehicle_models_to_view(): void
    {
        \App\Models\VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->get(route('shoppings.create'));

        $response->assertStatus(200);
        $response->assertSee('Fortuner');
    }

    public function test_show_displays_shopping(): void
    {
        $shopping = Shopping::factory()->create();

        $response = $this->actingAs($this->user)->get(route('shoppings.show', $shopping));

        $response->assertStatus(200);
    }

    public function test_ship_deducts_stock(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();

        // Seed stock
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $response = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        $this->assertDatabaseHas('shoppings', ['id' => $shopping->id, 'status' => 'shipped']);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 12]);
        $response->assertRedirect();
    }

    public function test_ship_fails_on_insufficient_stock(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $response = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        $response->assertRedirect();
        $this->assertDatabaseHas('shoppings', ['id' => $shopping->id, 'status' => 'draft']); // unchanged
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'quantity' => 3]); // unchanged
    }

    public function test_destroy_deletes_draft_only(): void
    {
        $shopping = Shopping::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user)->delete(route('shoppings.destroy', $shopping));

        $this->assertDatabaseMissing('shoppings', ['id' => $shopping->id]);
    }

    public function test_store_validates_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('shoppings.store'), []);

        $response->assertSessionHasErrors(['partner_name', 'shopping_date', 'items']);
    }

    public function test_create_passes_products_with_stocks_to_view(): void
    {
        $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
        $rack = Rack::factory()->create();
        $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 7]);

        $response = $this->actingAs($this->user)->get(route('shoppings.create'));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
    }

    public function test_edit_passes_products_with_stocks_to_view(): void
    {
        $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
        $rack = Rack::factory()->create();
        $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);
        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('shoppings.edit', $shopping));

        $response->assertStatus(200);
    }

    public function test_store_merges_duplicate_product_and_rack_items(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shopping_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shoppings.store'), $data);

        $response->assertRedirect();
        $shopping = Shopping::first();
        $this->assertCount(1, $shopping->items);
        $this->assertDatabaseHas('shopping_items', [
            'shopping_id' => $shopping->id,
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
            'shopping_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rackA->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rackB->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shoppings.store'), $data);

        $response->assertRedirect();
        $shopping = Shopping::first();
        $this->assertCount(2, $shopping->items);
    }

    public function test_ship_second_call_is_rejected_and_stock_not_double_deducted(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $first = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));
        $first->assertRedirect();

        $second = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));
        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 12,
        ]);
    }

    public function test_ship_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        Event::assertDispatched(StockChanged::class);
    }

    public function test_ship_does_not_dispatch_stock_changed_event_when_insufficient_stock(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        Event::assertNotDispatched(StockChanged::class);
    }
}
