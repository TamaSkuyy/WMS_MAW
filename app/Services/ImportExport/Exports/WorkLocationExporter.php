<?php

namespace App\Services\ImportExport\Exports;

use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class WorkLocationExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama'];
    }

    public function exportQuery(): Builder
    {
        return WorkLocation::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name];
    }
}
