<?php

namespace App\Services\ImportExport\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface Exportable
{
    /** @return array<string> Column heading labels */
    public function headings(): array;

    /** Base query builder for the export dataset */
    public function exportQuery(): Builder;

    /** @return array<string, mixed> Map a single model to a flat row */
    public function mapRow($model): array;
}
