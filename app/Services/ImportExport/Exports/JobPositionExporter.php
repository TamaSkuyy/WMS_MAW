<?php

namespace App\Services\ImportExport\Exports;

use App\Models\JobPosition;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class JobPositionExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Level'];
    }

    public function exportQuery(): Builder
    {
        return JobPosition::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->level];
    }
}
