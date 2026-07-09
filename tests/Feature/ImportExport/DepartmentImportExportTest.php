<?php

namespace Tests\Feature\ImportExport;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DepartmentImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Department::factory()->create();

        $response = $this->get(route('departments.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Department::factory()->create();

        $response = $this->get(route('departments.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('departments.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'departments.csv',
            "Nama\nLogistik"
        );

        $response = $this->post(route('departments.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('departments', ['name' => 'Logistik']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Department::factory()->create(['name' => 'Operasional']);

        $file = UploadedFile::fake()->createWithContent(
            'departments.csv',
            "Nama\nOperasional"
        );

        $response = $this->post(route('departments.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
