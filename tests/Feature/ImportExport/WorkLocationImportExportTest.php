<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WorkLocationImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        WorkLocation::factory()->create();

        $response = $this->get(route('work-locations.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        WorkLocation::factory()->create();

        $response = $this->get(route('work-locations.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('work-locations.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'locations.csv',
            "Nama\nGudang Cikarang"
        );

        $response = $this->post(route('work-locations.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_locations', ['name' => 'Gudang Cikarang']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        WorkLocation::factory()->create(['name' => 'Gudang Bekasi']);

        $file = UploadedFile::fake()->createWithContent(
            'locations.csv',
            "Nama\nGudang Bekasi"
        );

        $response = $this->post(route('work-locations.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
