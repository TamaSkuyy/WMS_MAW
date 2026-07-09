<?php

namespace Tests\Feature\ImportExport;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RackImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Rack::factory()->create(['code' => 'A1', 'zone' => 'Zone A']);

        $response = $this->get(route('racks.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Rack::factory()->create(['code' => 'A1', 'zone' => 'Zone A']);

        $response = $this->get(route('racks.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('racks.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'racks.csv',
            "Kode,Zona\nC1,Zone C\nC2,Zone C"
        );

        $response = $this->post(route('racks.import'), [
            'file' => $file,
            'column_mapping' => ['code' => 'Kode', 'zone' => 'Zona'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('racks', ['code' => 'C1', 'zone' => 'Zone C']);
        $this->assertDatabaseHas('racks', ['code' => 'C2', 'zone' => 'Zone C']);
    }

    public function test_import_skips_duplicate_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Rack::factory()->create(['code' => 'D1', 'zone' => 'Zone D']);

        $file = UploadedFile::fake()->createWithContent(
            'racks.csv',
            "Kode,Zona\nD1,Zone D Updated"
        );

        $response = $this->post(route('racks.import'), [
            'file' => $file,
            'column_mapping' => ['code' => 'Kode', 'zone' => 'Zona'],
        ]);

        $response->assertOk();
        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1, 'processed_rows' => 0]);
    }
}
