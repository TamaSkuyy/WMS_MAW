<?php

namespace Tests\Feature\ImportExport;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductCategoryImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ProductCategory::factory()->create();

        $response = $this->get(route('product-categories.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ProductCategory::factory()->create();

        $response = $this->get(route('product-categories.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('product-categories.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'categories.csv',
            "Nama,Deskripsi\nBody Parts,Panel bodi kendaraan"
        );

        $response = $this->post(route('product-categories.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'description' => 'Deskripsi'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_categories', ['name' => 'Body Parts', 'description' => 'Panel bodi kendaraan']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ProductCategory::factory()->create(['name' => 'Engine Components']);

        $file = UploadedFile::fake()->createWithContent(
            'categories.csv',
            "Nama,Deskripsi\nEngine Components,Duplicate"
        );

        $response = $this->post(route('product-categories.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'description' => 'Deskripsi'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
