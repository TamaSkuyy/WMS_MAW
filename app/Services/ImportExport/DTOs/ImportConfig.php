<?php

namespace App\Services\ImportExport\DTOs;

use App\Services\ImportExport\Enums\ImportFormat;

readonly class ImportConfig
{
    public function __construct(
        public ImportFormat $format,
        public string $filePath,
        public string $modelType,
        public array $columnMapping,
        public array $validationRules,
        public string $uniqueKey,
        public int $chunkSize = 500,
    ) {}
}
