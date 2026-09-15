<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kolom pencarian di menu Receiving/Cycles: supplier, nomor cycle, part number.
 */
class CycleSearchTest extends TestCase
{
    use RefreshDatabase;

    private function pageProps($response): array
    {
        return json_decode(json_encode($response->viewData('page')), true)['props'];
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('view cycles'));

        return $user;
    }

    private function makeCycle(Supplier $supplier, int $number): Cycle
    {
        return Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'cycle_number' => $number,
            'status' => 'draft',
        ]);
    }

    public function test_search_by_supplier_name_or_code(): void
    {
        $dwa = Supplier::factory()->create(['name' => 'DWA Abadi', 'code' => 'DWA']);
        $mmm = Supplier::factory()->create(['name' => 'MMM Sentosa', 'code' => 'MMM']);

        $this->makeCycle($dwa, 1);
        $this->makeCycle($mmm, 1);

        $response = $this->actingAs($this->actingUser())
            ->get(route('cycles.index', ['search' => 'DWA']));

        $rows = $this->pageProps($response)['cycles']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('DWA Abadi', $rows[0]['supplier']['name']);
    }

    public function test_search_by_part_number(): void
    {
        $dwa = Supplier::factory()->create(['name' => 'DWA Abadi', 'code' => 'DWA']);
        $product = Product::factory()->create(['part_number' => 'P5162-0KA08']);

        $withPart = $this->makeCycle($dwa, 1);
        CycleItem::factory()->create([
            'cycle_id' => $withPart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'received_quantity' => 0,
        ]);
        $this->makeCycle($dwa, 2);

        $response = $this->actingAs($this->actingUser())
            ->get(route('cycles.index', ['search' => 'P5162-0KA08']));

        $rows = $this->pageProps($response)['cycles']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame($withPart->id, $rows[0]['id']);
    }

    public function test_search_by_cycle_number(): void
    {
        $dwa = Supplier::factory()->create(['name' => 'DWA Abadi', 'code' => 'DWA']);
        $this->makeCycle($dwa, 7);
        $this->makeCycle($dwa, 8);

        $response = $this->actingAs($this->actingUser())
            ->get(route('cycles.index', ['search' => '7']));

        $rows = $this->pageProps($response)['cycles']['data'];
        $this->assertCount(1, $rows);
        $this->assertSame(7, $rows[0]['cycle_number']);
    }

    public function test_without_search_returns_all(): void
    {
        $dwa = Supplier::factory()->create(['name' => 'DWA Abadi', 'code' => 'DWA']);
        $this->makeCycle($dwa, 1);
        $this->makeCycle($dwa, 2);

        $response = $this->actingAs($this->actingUser())->get(route('cycles.index'));

        $this->assertCount(2, $this->pageProps($response)['cycles']['data']);
    }
}
