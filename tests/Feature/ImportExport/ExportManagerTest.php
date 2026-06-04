<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\UserExporter;
use App\Services\ImportExport\Managers\ExportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_export_downloads_file(): void
    {
        User::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $exporter = new UserExporter();
        $config = new ExportConfig(
            format: ExportFormat::Xlsx,
            fileName: 'test-export',
            headings: $exporter->headings(),
            columns: ['name', 'email', 'role', 'created_at'],
            exportableClass: UserExporter::class,
        );

        $manager = app(ExportManager::class);
        $response = $manager->download($exporter, $config);

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition') ?? '');
    }

    public function test_csv_export_downloads_file(): void
    {
        User::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $exporter = new UserExporter();
        $config = new ExportConfig(
            format: ExportFormat::Csv,
            fileName: 'test-export',
            headings: $exporter->headings(),
            columns: ['name', 'email', 'role', 'created_at'],
            exportableClass: UserExporter::class,
        );

        $manager = app(ExportManager::class);
        $response = $manager->download($exporter, $config);

        $this->assertEquals('text/csv', $response->headers->get('content-type'));
    }
}
