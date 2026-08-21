<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ImportExport\Exports\SupplierPerformanceExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupplierPerformanceExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('view supplier performance'));
    }

    private function completedCycle(array $attributes = []): Cycle
    {
        return Cycle::factory()->create(array_merge([
            'status' => 'completed',
            'received_at' => now(),
        ], $attributes));
    }

    public function test_export_xlsx_returns_success(): void
    {
        $cycle = $this->completedCycle();
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'quantity' => 5, 'received_quantity' => 5]);

        $response = $this->actingAs($this->user)->get(route('reports.supplier-performance.export', ['format' => 'xlsx']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_pdf_returns_success(): void
    {
        $cycle = $this->completedCycle();
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'quantity' => 5, 'received_quantity' => 5]);

        $response = $this->actingAs($this->user)->get(route('reports.supplier-performance.export', ['format' => 'pdf']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_exporter_filters_by_supplier_and_date_range(): void
    {
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $inRange = $this->completedCycle(['supplier_id' => $supplierA->id, 'received_at' => '2026-06-15 10:00:00']);
        CycleItem::factory()->create(['cycle_id' => $inRange->id]);

        $outOfRange = $this->completedCycle(['supplier_id' => $supplierA->id, 'received_at' => '2026-05-01 10:00:00']);
        CycleItem::factory()->create(['cycle_id' => $outOfRange->id]);

        $otherSupplier = $this->completedCycle(['supplier_id' => $supplierB->id, 'received_at' => '2026-06-10 10:00:00']);
        CycleItem::factory()->create(['cycle_id' => $otherSupplier->id]);

        $exporter = new SupplierPerformanceExporter([
            'supplier_id' => (string) $supplierA->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]);

        $ids = $exporter->exportQuery()->pluck('id');

        $this->assertEqualsCanonicalizing([$inRange->id], $ids->all());
    }

    public function test_exporter_maps_on_time_complete_cycle(): void
    {
        $cycle = $this->completedCycle(['received_at' => '2026-06-15 10:00:00', 'delivery_date' => '2026-06-15']);
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'quantity' => 5, 'received_quantity' => 5]);

        $row = (new SupplierPerformanceExporter([]))->mapRow($cycle->fresh(['supplier', 'items']));

        $this->assertSame($cycle->cycle_number, $row[0]);
        $this->assertSame($cycle->supplier->name, $row[1]);
        $this->assertSame(1, $row[4]);
        $this->assertSame(1, $row[5]);
        $this->assertSame('Tepat Waktu', $row[6]);
    }

    public function test_exporter_maps_late_cycle_and_shortfall(): void
    {
        $lateCycle = $this->completedCycle(['received_at' => '2026-06-20 10:00:00', 'delivery_date' => '2026-06-15']);
        CycleItem::factory()->create(['cycle_id' => $lateCycle->id, 'quantity' => 5, 'received_quantity' => 5]);

        $shortfallCycle = $this->completedCycle(['received_at' => '2026-06-10 10:00:00', 'delivery_date' => '2026-06-15']);
        CycleItem::factory()->create(['cycle_id' => $shortfallCycle->id, 'quantity' => 5, 'received_quantity' => 3]);

        $exporter = new SupplierPerformanceExporter([]);

        $lateRow = $exporter->mapRow($lateCycle->fresh(['supplier', 'items']));
        $this->assertSame('Terlambat', $lateRow[6]);

        $shortfallRow = $exporter->mapRow($shortfallCycle->fresh(['supplier', 'items']));
        $this->assertSame('Kurang', $shortfallRow[6]);
        $this->assertSame(0, $shortfallRow[4]);
        $this->assertSame(1, $shortfallRow[5]);
    }
}
