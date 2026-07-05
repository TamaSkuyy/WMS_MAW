<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function slidesFrom($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props']['slides'];
    }

    public function test_index_is_accessible_without_authentication(): void
    {
        $response = $this->get(route('tv-dashboard'));

        $response->assertStatus(200);
    }

    public function test_index_chunks_active_products_into_slides_of_six(): void
    {
        Product::factory(8)->create(['is_active' => true]);

        $response = $this->get(route('tv-dashboard'));

        $slides = $this->slidesFrom($response);

        $this->assertCount(2, $slides);
        $this->assertCount(6, $slides[0]);
        $this->assertCount(2, $slides[1]);
    }

    public function test_index_excludes_inactive_products(): void
    {
        Product::factory()->create(['is_active' => true, 'name' => 'Active Item']);
        Product::factory()->create(['is_active' => false, 'name' => 'Inactive Item']);

        $response = $this->get(route('tv-dashboard'));

        $names = collect($this->slidesFrom($response))->flatten(1)->pluck('name');

        $this->assertTrue($names->contains('Active Item'));
        $this->assertFalse($names->contains('Inactive Item'));
    }

    public function test_index_includes_total_stock_summed_across_racks(): void
    {
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rackA->id, 'quantity' => 5]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rackB->id, 'quantity' => 7]);

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertSame(12, $item['total_stock']);
    }

    public function test_index_includes_last_received_summary_for_product_with_completed_cycle(): void
    {
        $product = Product::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => '2026-07-01 10:00:00']);
        CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'received_quantity' => 15,
        ]);

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertSame(15, $item['last_received']['quantity']);
    }

    public function test_index_includes_null_last_received_for_product_without_history(): void
    {
        $product = Product::factory()->create();

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertNull($item['last_received']);
    }

    public function test_index_includes_last_shipped_summary_for_product_with_shipped_shipment(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $shipment = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 9,
        ]);

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertSame(9, $item['last_shipped']['quantity']);
    }

    public function test_index_includes_null_last_shipped_for_product_without_history(): void
    {
        $product = Product::factory()->create();

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertNull($item['last_shipped']);
    }
}
