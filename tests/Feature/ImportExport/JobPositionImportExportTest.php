<?php

namespace Tests\Feature\ImportExport;

use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class JobPositionImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        JobPosition::factory()->create();

        $response = $this->get(route('job-positions.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        JobPosition::factory()->create();

        $response = $this->get(route('job-positions.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('job-positions.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'positions.csv',
            "Nama,Level\nStaff Gudang,Staff"
        );

        $response = $this->post(route('job-positions.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'level' => 'Level'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('job_positions', ['name' => 'Staff Gudang', 'level' => 'Staff']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        JobPosition::factory()->create(['name' => 'Manager Gudang']);

        $file = UploadedFile::fake()->createWithContent(
            'positions.csv',
            "Nama,Level\nManager Gudang,Manager"
        );

        $response = $this->post(route('job-positions.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'level' => 'Level'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
