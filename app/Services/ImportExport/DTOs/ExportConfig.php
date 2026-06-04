<?php

namespace App\Services\ImportExport\DTOs;

use App\Services\ImportExport\Enums\ExportFormat;

readonly class ExportConfig
{
    public function __construct(
        public ExportFormat $format,
        public string $fileName,
        public array $headings,
        public array $columns,
        public string $exportableClass,
        /** @var array<string, mixed> */
        public array $filters = [],
    ) {}
}
