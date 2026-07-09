<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Department;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class DepartmentExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama'];
    }

    public function exportQuery(): Builder
    {
        return Department::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name];
    }
}
