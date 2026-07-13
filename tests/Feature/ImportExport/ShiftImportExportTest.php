<?php

namespace Tests\Feature\ImportExport;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ShiftImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Shift::factory()->create();

        $response = $this->get(route('shifts.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Shift::factory()->create();

        $response = $this->get(route('shifts.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('shifts.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'shifts.csv',
            "Nama,Kode,Jam Mulai,Jam Selesai,Status\nShift Pagi,P,08:00,16:00,Aktif"
        );

        $response = $this->post(route('shifts.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'code' => 'Kode',
                'start_time' => 'Jam Mulai',
                'end_time' => 'Jam Selesai',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('shifts', ['name' => 'Shift Pagi', 'code' => 'P']);
    }

    public function test_import_skips_duplicate_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Shift::factory()->create(['name' => 'Shift Pagi', 'code' => 'P']);

        $file = UploadedFile::fake()->createWithContent(
            'shifts.csv',
            "Nama,Kode,Jam Mulai,Jam Selesai,Status\nShift Pagi Lain,P,09:00,17:00,Aktif"
        );

        $response = $this->post(route('shifts.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'code' => 'Kode',
                'start_time' => 'Jam Mulai',
                'end_time' => 'Jam Selesai',
                'status' => 'Status',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
