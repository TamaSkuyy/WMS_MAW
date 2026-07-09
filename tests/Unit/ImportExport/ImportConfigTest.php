<?php

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Imports\UserImporter;
use Tests\TestCase;

class ImportConfigTest extends TestCase
{
    public function test_import_config_has_correct_defaults(): void
    {
        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: '/tmp/test.csv',
            modelType: 'App\Models\User',
            columnMapping: ['email' => 'A'],
            validationRules: ['email' => ['required']],
            uniqueKey: 'email',
            importerClass: UserImporter::class,
        );

        $this->assertEquals(ImportFormat::Csv, $config->format);
        $this->assertEquals('/tmp/test.csv', $config->filePath);
        $this->assertEquals(500, $config->chunkSize);
        $this->assertEquals('email', $config->uniqueKey);
        $this->assertEquals(UserImporter::class, $config->importerClass);
    }
}
