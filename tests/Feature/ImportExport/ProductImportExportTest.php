<?php

namespace Tests\Feature\ImportExport;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create();

        $response = $this->get(route('products.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create();

        $response = $this->get(route('products.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('products.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_resolves_relations_and_creates_product(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vehicleModel = VehicleModel::factory()->create(['name' => 'Avanza', 'brand' => 'Toyota']);
        $supplier = Supplier::factory()->create(['name' => 'PT Onderdil Jaya']);
        $category = ProductCategory::factory()->create(['name' => 'Body Parts']);
        $rack = Rack::factory()->create(['code' => 'A-01']);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "PartNumber,Nama,Merek,Model,Supplier,Kategori,Satuan,Deskripsi,Harga,Aktif,Rak\n"
            . "P0001-AVZ,Bumper Depan,Toyota,Avanza,PT Onderdil Jaya,Body Parts,pcs,Bumper original,250000,1,A-01"
        );

        $response = $this->post(route('products.import'), [
            'file' => $file,
            'column_mapping' => [
                'part_number' => 'PartNumber',
                'name' => 'Nama',
                'brand' => 'Merek',
                'model_kendaraan' => 'Model',
                'supplier' => 'Supplier',
                'kategori' => 'Kategori',
                'unit' => 'Satuan',
                'description' => 'Deskripsi',
                'base_price' => 'Harga',
                'is_active' => 'Aktif',
                'default_rack' => 'Rak',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'part_number' => 'P0001-AVZ',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'default_rack_id' => $rack->id,
        ]);
    }

    public function test_import_records_error_when_vehicle_model_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Supplier::factory()->create(['name' => 'PT Onderdil Jaya']);
        ProductCategory::factory()->create(['name' => 'Body Parts']);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "PartNumber,Nama,Merek,Model,Supplier,Kategori,Satuan\n"
            . "P0002-XXX,Lampu Belakang,Toyota,Model Tidak Ada,PT Onderdil Jaya,Body Parts,pcs"
        );

        $response = $this->post(route('products.import'), [
            'file' => $file,
            'column_mapping' => [
                'part_number' => 'PartNumber',
                'name' => 'Nama',
                'brand' => 'Merek',
                'model_kendaraan' => 'Model',
                'supplier' => 'Supplier',
                'kategori' => 'Kategori',
                'unit' => 'Satuan',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'processed_rows' => 0]);
        $this->assertDatabaseMissing('products', ['part_number' => 'P0002-XXX']);

        $log = \App\Services\ImportExport\Models\ImportLog::find($importLogId);
        $this->assertNotEmpty($log->errors);
    }

    public function test_import_skips_duplicate_part_number(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vehicleModel = VehicleModel::factory()->create(['name' => 'Avanza', 'brand' => 'Toyota']);
        $supplier = Supplier::factory()->create(['name' => 'PT Onderdil Jaya']);
        $category = ProductCategory::factory()->create(['name' => 'Body Parts']);
        Product::factory()->create([
            'part_number' => 'P0003-DUP',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "PartNumber,Nama,Merek,Model,Supplier,Kategori,Satuan\n"
            . "P0003-DUP,Bumper Depan,Toyota,Avanza,PT Onderdil Jaya,Body Parts,pcs"
        );

        $response = $this->post(route('products.import'), [
            'file' => $file,
            'column_mapping' => [
                'part_number' => 'PartNumber',
                'name' => 'Nama',
                'brand' => 'Merek',
                'model_kendaraan' => 'Model',
                'supplier' => 'Supplier',
                'kategori' => 'Kategori',
                'unit' => 'Satuan',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
