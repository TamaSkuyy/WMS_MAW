<?php

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Enums\ImportStatus;
use Tests\TestCase;

class EnumsTest extends TestCase
{
    public function test_import_format_has_expected_cases(): void
    {
        $this->assertEquals('xlsx', ImportFormat::Xlsx->value);
        $this->assertEquals('csv', ImportFormat::Csv->value);
        $this->assertCount(2, ImportFormat::cases());
    }

    public function test_export_format_has_expected_cases(): void
    {
        $this->assertCount(3, ExportFormat::cases());
        $this->assertEquals('pdf', ExportFormat::Pdf->value);
    }

    public function test_import_status_has_expected_cases(): void
    {
        $cases = ImportStatus::cases();
        $this->assertCount(4, $cases);

        $values = array_map(fn ($c) => $c->value, $cases);
        $this->assertContains('pending', $values);
        $this->assertContains('processing', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('failed', $values);
    }
}
