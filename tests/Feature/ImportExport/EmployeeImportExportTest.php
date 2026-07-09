<?php

namespace Tests\Feature\ImportExport;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Employee::factory()->create();

        $response = $this->get(route('employees.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Employee::factory()->create();

        $response = $this->get(route('employees.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('employees.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_resolves_optional_relations_and_stamps_audit_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $position = JobPosition::factory()->create(['name' => 'Staff Gudang']);
        $location = WorkLocation::factory()->create(['name' => 'Gudang Utama']);
        $department = Department::factory()->create(['name' => 'Logistik']);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Telepon,Email,Status\n"
            . "Andi Saputra,NIK-0001,Staff Gudang,Gudang Utama,Logistik,08123456789,andi@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'nik' => 'NIK-0001',
            'job_position_id' => $position->id,
            'work_location_id' => $location->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_import_leaves_relation_null_when_optional_lookup_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Telepon,Email,Status\n"
            . "Budi Santoso,NIK-0002,Jabatan Tidak Ada,,,08123456780,budi@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'nik' => 'NIK-0002',
            'job_position_id' => null,
            'work_location_id' => null,
            'department_id' => null,
        ]);
    }

    public function test_import_skips_duplicate_nik(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Employee::factory()->create(['nik' => 'NIK-0003']);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Telepon,Email,Status\n"
            . "Citra Dewi,NIK-0003,,,,08123456781,citra@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
