<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pencarian & filter halaman Inventori Stok (Transactions > Stocks).
 *
 * Melindungi: pencarian multi-kata, pencarian lewat relasi (rak, zona, supplier,
 * model kendaraan), kata kunci "relay", filter status, urutan, ringkasan
 * (summary) hasil filter, dan batas baris per halaman.
 */
class StockIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('view stocks'));
    }

    /** @return array<int,array> */
    private function rows(array $query = []): array
    {
        $response = $this->actingAs($this->user)->get(route('stocks.index', $query));
        $response->assertOk();

        return $response->viewData('page')['props']['stocks']['data'];
    }

    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->user)->get(route('stocks.index', $query));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_no_filter_returns_everything_with_summary(): void
    {
        $supplier = Supplier::factory()->create();
        $rack = Rack::factory()->create(['code' => 'A-01', 'zone' => 'Zona A']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id, 'part_number' => 'P1000-AA001']);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 7]);
        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 3]);

        $props = $this->props();

        $this->assertCount(2, $props['stocks']['data']);
        $this->assertSame(2, $props['summary']['rows']);
        $this->assertSame(10, $props['summary']['quantity']);
        $this->assertSame(1, $props['summary']['products']);
        $this->assertSame(1, $props['summary']['relay_rows']);
        $this->assertSame(3, $props['summary']['relay_quantity']);
    }

    public function test_search_matches_part_number_and_product_name(): void
    {
        $match = Product::factory()->create(['part_number' => 'P5162-0KA08', 'name' => 'Side Visor Innova']);
        $other = Product::factory()->create(['part_number' => 'P9999-XX999', 'name' => 'Barang Lain']);
        Stock::create(['product_id' => $match->id, 'rack_id' => null, 'quantity' => 5]);
        Stock::create(['product_id' => $other->id, 'rack_id' => null, 'quantity' => 5]);

        $this->assertSame(['P5162-0KA08'], $this->partNumbers($this->rows(['search' => '0KA08'])));
        $this->assertSame(['P5162-0KA08'], $this->partNumbers($this->rows(['search' => 'visor'])));
        $this->assertSame([], $this->rows(['search' => 'tidak-ada-sama-sekali']));
    }

    public function test_search_is_multi_word_and_must_match_all_terms(): void
    {
        $both = Product::factory()->create(['part_number' => 'AAA-1', 'name' => 'Side Visor Avanza']);
        $onlyOne = Product::factory()->create(['part_number' => 'BBB-2', 'name' => 'Side Visor Veloz']);
        Stock::create(['product_id' => $both->id, 'rack_id' => null, 'quantity' => 1]);
        Stock::create(['product_id' => $onlyOne->id, 'rack_id' => null, 'quantity' => 1]);

        $this->assertSame(['AAA-1'], $this->partNumbers($this->rows(['search' => 'visor avanza'])));
    }

    public function test_search_matches_rack_zone_supplier_and_vehicle_model(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'PT Mitra Jaya']);
        $vehicle = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Fortuner', 'suffix' => 'GR']);
        $rack = Rack::factory()->create(['code' => 'BUF-M01', 'zone' => 'Buffer']);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'vehicle_model_id' => $vehicle->id,
            'part_number' => 'P5154-0KA2F',
            'name' => 'FR Bumper',
        ]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 4]);

        $this->assertCount(1, $this->rows(['search' => 'BUF-M01']), 'cari kode rak');
        $this->assertCount(1, $this->rows(['search' => 'buffer']), 'cari zona');
        $this->assertCount(1, $this->rows(['search' => 'Mitra Jaya']), 'cari supplier');
        $this->assertCount(1, $this->rows(['search' => 'fortuner']), 'cari model kendaraan');
        $this->assertCount(1, $this->rows(['search' => 'GR']), 'cari suffix model');
    }

    public function test_search_keyword_relay_only_returns_rows_without_rack(): void
    {
        $product = Product::factory()->create(['part_number' => 'REL-1']);
        $rack = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);
        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 2]);

        $rows = $this->rows(['search' => 'relay']);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['rack_id']);
    }

    public function test_status_filter_distinguishes_rack_relay_zero_and_low(): void
    {
        $rack = Rack::factory()->create();
        $normal = Product::factory()->create(['part_number' => 'NRM-1', 'min_stock' => 10]);
        $low = Product::factory()->create(['part_number' => 'LOW-1', 'min_stock' => 10]);
        $zero = Product::factory()->create(['part_number' => 'ZRO-1']);

        Stock::create(['product_id' => $normal->id, 'rack_id' => $rack->id, 'quantity' => 50]);
        Stock::create(['product_id' => $low->id, 'rack_id' => $rack->id, 'quantity' => 2]);
        Stock::create(['product_id' => $zero->id, 'rack_id' => $rack->id, 'quantity' => 0]);
        Stock::create(['product_id' => $zero->id, 'rack_id' => null, 'quantity' => 9]);

        $this->assertSame(['NRM-1', 'LOW-1', 'ZRO-1'], $this->partNumbers($this->rows(['status' => 'rack'])));
        $this->assertSame(['ZRO-1'], $this->partNumbers($this->rows(['status' => 'relay'])));
        $this->assertSame(['ZRO-1'], $this->partNumbers($this->rows(['status' => 'zero'])));
        $this->assertSame(['LOW-1'], $this->partNumbers($this->rows(['status' => 'low'])));

        $props = $this->props(['status' => 'low']);
        $this->assertSame(1, $props['summary']['low_rows']);
        $this->assertSame(2, $props['summary']['quantity']);
    }

    public function test_rack_zone_and_supplier_filters_combine(): void
    {
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $rackA = Rack::factory()->create(['code' => 'A-01', 'zone' => 'Zona A']);
        $rackB = Rack::factory()->create(['code' => 'B-01', 'zone' => 'Zona B']);
        $productA = Product::factory()->create(['supplier_id' => $supplierA->id, 'part_number' => 'AAA-1']);
        $productB = Product::factory()->create(['supplier_id' => $supplierB->id, 'part_number' => 'BBB-2']);

        Stock::create(['product_id' => $productA->id, 'rack_id' => $rackA->id, 'quantity' => 3]);
        Stock::create(['product_id' => $productB->id, 'rack_id' => $rackB->id, 'quantity' => 4]);

        $this->assertSame(['AAA-1'], $this->partNumbers($this->rows(['rack_id' => $rackA->id])));
        $this->assertSame(['AAA-1'], $this->partNumbers($this->rows(['zone' => 'Zona A'])));
        $this->assertSame(['AAA-1'], $this->partNumbers($this->rows(['supplier_id' => $supplierA->id])));
        $this->assertSame(['AAA-1'], $this->partNumbers($this->rows([
            'zone' => 'Zona A',
            'supplier_id' => $supplierA->id,
            'search' => 'AAA',
        ])));
    }

    public function test_product_id_filter_returns_only_that_product(): void
    {
        $wanted = Product::factory()->create(['part_number' => 'WNT-1']);
        $other = Product::factory()->create(['part_number' => 'OTH-2']);
        Stock::create(['product_id' => $wanted->id, 'rack_id' => null, 'quantity' => 1]);
        Stock::create(['product_id' => $other->id, 'rack_id' => null, 'quantity' => 1]);

        $props = $this->props(['product_id' => $wanted->id]);

        $this->assertSame(['WNT-1'], $this->partNumbers($props['stocks']['data']));
        $this->assertSame('WNT-1', $props['activeProduct']['part_number']);
    }

    public function test_sort_part_number_ascending(): void
    {
        foreach (['CCC-3', 'AAA-1', 'BBB-2'] as $part) {
            $product = Product::factory()->create(['part_number' => $part]);
            Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 1]);
        }

        $this->assertSame(['AAA-1', 'BBB-2', 'CCC-3'], $this->partNumbers($this->rows(['sort' => 'part_asc'])));
    }

    public function test_default_page_size_is_25_and_can_be_changed(): void
    {
        $rack = Rack::factory()->create();

        for ($i = 1; $i <= 30; $i++) {
            $product = Product::factory()->create(['part_number' => sprintf('PAGE-%03d', $i)]);
            Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => $i]);
        }

        $this->assertCount(25, $this->rows());
        $this->assertCount(10, $this->rows(['per_page' => 10]));

        // per_page di luar pilihan diabaikan (kembali ke default 25).
        $props = $this->props(['per_page' => 999]);
        $this->assertSame(25, $props['stocks']['per_page']);
    }

    /** @param array<int,array> $rows @return array<int,string> */
    private function partNumbers(array $rows): array
    {
        return array_map(fn ($row) => $row['product']['part_number'], $rows);
    }
}
