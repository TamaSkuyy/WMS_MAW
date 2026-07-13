<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function pageProps($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props'];
    }

    public function test_receiving_report_returns_200(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.receiving'));

        $response->assertStatus(200);
    }

    public function test_receiving_report_filters_by_date_range(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $inRange = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => '2026-06-15 10:00:00',
        ]);
        CycleItem::factory()->create(['cycle_id' => $inRange->id, 'product_id' => $product->id, 'received_quantity' => 5]);

        $outOfRange = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => '2026-05-01 10:00:00',
        ]);
        CycleItem::factory()->create(['cycle_id' => $outOfRange->id, 'product_id' => $product->id, 'received_quantity' => 7]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['received_quantity']);
    }

    public function test_receiving_report_filters_by_supplier(): void
    {
        $product = Product::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $cycleA = Cycle::factory()->create(['supplier_id' => $supplierA->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycleA->id, 'product_id' => $product->id, 'received_quantity' => 3]);

        $cycleB = Cycle::factory()->create(['supplier_id' => $supplierB->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycleB->id, 'product_id' => $product->id, 'received_quantity' => 9]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving', ['supplier_id' => $supplierA->id]));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['received_quantity']);
    }

    public function test_receiving_report_filters_by_status(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $draftCycle = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'draft', 'received_at' => null]);
        CycleItem::factory()->create(['cycle_id' => $draftCycle->id, 'product_id' => $product->id, 'received_quantity' => 0]);

        $completedCycle = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $completedCycle->id, 'product_id' => $product->id, 'received_quantity' => 4]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving', ['status' => 'completed']));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(4, $rows[0]['received_quantity']);
    }

    public function test_receiving_report_summary_totals(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $cycle1 = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle1->id, 'product_id' => $productA->id, 'received_quantity' => 5]);
        CycleItem::factory()->create(['cycle_id' => $cycle1->id, 'product_id' => $productB->id, 'received_quantity' => 3]);

        $cycle2 = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle2->id, 'product_id' => $productA->id, 'received_quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving'));

        $summary = $this->pageProps($response)['summary'];

        $this->assertSame(2, $summary['total_transactions']);
        $this->assertSame(10, $summary['total_quantity']);
        $this->assertSame(2, $summary['unique_products']);
    }

    public function test_receiving_report_export_xlsx_returns_success(): void
    {
        $product = Product::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'product_id' => $product->id, 'received_quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving.export', ['format' => 'xlsx']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_receiving_report_export_pdf_returns_success(): void
    {
        $product = Product::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'product_id' => $product->id, 'received_quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving.export', ['format' => 'pdf']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_shopping_report_returns_200(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.shopping'));

        $response->assertStatus(200);
    }

    public function test_shopping_report_filters_by_date_range(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $inRange = Shopping::factory()->create(['status' => 'shipped', 'shopping_date' => '2026-06-15']);
        ShoppingItem::factory()->create(['shopping_id' => $inRange->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 6]);

        $outOfRange = Shopping::factory()->create(['status' => 'shipped', 'shopping_date' => '2026-05-01']);
        ShoppingItem::factory()->create(['shopping_id' => $outOfRange->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $response = $this->actingAs($this->user)->get(route('reports.shopping', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(6, $rows[0]['quantity']);
    }

    public function test_shopping_report_filters_by_partner_text_search(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $matching = Shopping::factory()->create(['status' => 'shipped', 'partner_name' => 'PT Maju Jaya']);
        ShoppingItem::factory()->create(['shopping_id' => $matching->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 4]);

        $other = Shopping::factory()->create(['status' => 'shipped', 'partner_name' => 'CV Sentosa']);
        ShoppingItem::factory()->create(['shopping_id' => $other->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('reports.shopping', ['partner' => 'Maju']));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(4, $rows[0]['quantity']);
    }

    public function test_shopping_report_filters_by_status(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $draft = Shopping::factory()->create(['status' => 'draft']);
        ShoppingItem::factory()->create(['shopping_id' => $draft->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 1]);

        $shipped = Shopping::factory()->create(['status' => 'shipped']);
        ShoppingItem::factory()->create(['shopping_id' => $shipped->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

        $response = $this->actingAs($this->user)->get(route('reports.shopping', ['status' => 'shipped']));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['quantity']);
    }

    public function test_shopping_report_summary_totals(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $rack = Rack::factory()->create();

        $shopping1 = Shopping::factory()->create(['status' => 'shipped']);
        ShoppingItem::factory()->create(['shopping_id' => $shopping1->id, 'product_id' => $productA->id, 'rack_id' => $rack->id, 'quantity' => 5]);
        ShoppingItem::factory()->create(['shopping_id' => $shopping1->id, 'product_id' => $productB->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shopping2 = Shopping::factory()->create(['status' => 'shipped']);
        ShoppingItem::factory()->create(['shopping_id' => $shopping2->id, 'product_id' => $productA->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('reports.shopping'));

        $summary = $this->pageProps($response)['summary'];

        $this->assertSame(2, $summary['total_transactions']);
        $this->assertSame(10, $summary['total_quantity']);
        $this->assertSame(2, $summary['unique_products']);
    }

    public function test_shopping_report_export_xlsx_returns_success(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $shopping = Shopping::factory()->create(['status' => 'shipped']);
        ShoppingItem::factory()->create(['shopping_id' => $shopping->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.shopping.export', ['format' => 'xlsx']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_shopping_report_export_pdf_returns_success(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $shopping = Shopping::factory()->create(['status' => 'shipped']);
        ShoppingItem::factory()->create(['shopping_id' => $shopping->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.shopping.export', ['format' => 'pdf']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
