<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleModelImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create();

        $response = $this->get(route('vehicle-models.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create();

        $response = $this->get(route('vehicle-models.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('vehicle-models.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'vehicle-models.csv',
            "Nama,Merek,Suffix\nAvanza,Toyota,Veloz"
        );

        $response = $this->post(route('vehicle-models.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'brand' => 'Merek', 'suffix' => 'Suffix'],
        ]);

        $response->assertOk();
        $model = VehicleModel::where('name', 'Avanza')->where('brand', 'Toyota')->first();
        $this->assertNotNull($model);
        $this->assertSame('VELOZ', $model->suffix);
    }

    public function test_import_skips_duplicate_name_and_brand_combination(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create(['name' => 'Xenia', 'brand' => 'Daihatsu']);

        $file = UploadedFile::fake()->createWithContent(
            'vehicle-models.csv',
            "Nama,Merek,Suffix\nXenia,Daihatsu,R"
        );

        $response = $this->post(route('vehicle-models.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'brand' => 'Merek', 'suffix' => 'Suffix'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }

    public function test_import_does_not_skip_same_name_with_different_brand(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create(['name' => 'Xenia', 'brand' => 'Daihatsu']);

        $file = UploadedFile::fake()->createWithContent(
            'vehicle-models.csv',
            "Nama,Merek,Suffix\nXenia,Toyota,R"
        );

        $response = $this->post(route('vehicle-models.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'brand' => 'Merek', 'suffix' => 'Suffix'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'processed_rows' => 1, 'skipped_rows' => 0]);
        $this->assertDatabaseHas('vehicle_models', ['name' => 'Xenia', 'brand' => 'Toyota']);
    }
}
