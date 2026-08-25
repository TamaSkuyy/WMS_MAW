<?php

namespace Tests\Feature\ImportExport;

use App\Models\Cycle;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ImportExport\Exports\CycleExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_exporter_includes_pic_user_name(): void
    {
        $user = User::factory()->create(['name' => 'John Doe PIC']);
        $supplier = Supplier::factory()->create(['name' => 'Supplier ABC']);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'created_by' => $user->id,
            'cycle_number' => 1,
            'status' => 'draft',
            'notes' => 'Test notes',
        ]);

        $exporter = new CycleExporter();
        $headings = $exporter->headings();

        $this->assertContains('PIC', $headings);

        $row = $exporter->mapRow($cycle->fresh(['supplier', 'creator']));

        $picIndex = array_search('PIC', $headings);
        $this->assertSame('John Doe PIC', $row[$picIndex]);
    }

    public function test_cycle_export_http_endpoint_returns_success(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('export cycles'));
        $supplier = Supplier::factory()->create();
        Cycle::factory()->create(['supplier_id' => $supplier->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('cycles.export', ['format' => 'xlsx']));
        $response->assertOk();
    }
}
