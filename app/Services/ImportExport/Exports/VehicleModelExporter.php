<?php

namespace App\Services\ImportExport\Exports;

use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class VehicleModelExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Merek', 'Suffix'];
    }

    public function exportQuery(): Builder
    {
        return VehicleModel::query()->orderBy('brand')->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->brand, $model->suffix];
    }
}
