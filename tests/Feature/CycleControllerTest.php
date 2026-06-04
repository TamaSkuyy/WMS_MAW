<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $cycle->supplier_id,
            'cycle_number' => 5,
            'items' => [['product_id' => $product->id, 'quantity' => 20]],
        ];

        $response = $this->actingAs($this->user)->put(route('cycles.update', $cycle), $data);
        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'cycle_number' => 5]);
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
        $response->assertSessionHasErrors(['supplier_id', 'cycle_number', 'items']);
    }
}
