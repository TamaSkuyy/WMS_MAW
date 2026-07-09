<?php

namespace Tests\Feature\ImportExport;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SupplierImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Supplier::factory()->has(SupplierAddress::factory(), 'addresses')->create();

        $response = $this->get(route('suppliers.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Supplier::factory()->has(SupplierAddress::factory(), 'addresses')->create();

        $response = $this->get(route('suppliers.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('suppliers.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_supplier_and_primary_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'suppliers.csv',
            "Nama,Kontak,Email,Telepon,Jalan,Kota,Provinsi,KodePos,Negara\n"
            . "PT Sumber Jaya,Budi,budi@sumberjaya.test,08123456789,Jl. Industri 1,Bekasi,Jawa Barat,17530,Indonesia"
        );

        $response = $this->post(route('suppliers.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'contact_person' => 'Kontak',
                'email' => 'Email',
                'phone' => 'Telepon',
                'street' => 'Jalan',
                'city' => 'Kota',
                'state' => 'Provinsi',
                'postal_code' => 'KodePos',
                'country' => 'Negara',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('suppliers', ['name' => 'PT Sumber Jaya', 'email' => 'budi@sumberjaya.test']);
        $this->assertDatabaseHas('supplier_addresses', [
            'street' => 'Jl. Industri 1',
            'city' => 'Bekasi',
            'address_type' => 'primary',
        ]);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Supplier::factory()->create(['name' => 'PT Existing']);

        $file = UploadedFile::fake()->createWithContent(
            'suppliers.csv',
            "Nama,Kontak,Email,Telepon,Jalan,Kota,Provinsi,KodePos,Negara\n"
            . "PT Existing,Budi,new-email@test.test,08123456789,Jl. Baru 1,Bekasi,Jawa Barat,17530,Indonesia"
        );

        $response = $this->post(route('suppliers.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'contact_person' => 'Kontak',
                'email' => 'Email',
                'phone' => 'Telepon',
                'street' => 'Jalan',
                'city' => 'Kota',
                'state' => 'Provinsi',
                'postal_code' => 'KodePos',
                'country' => 'Negara',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
