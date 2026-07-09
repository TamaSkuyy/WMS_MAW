<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Rack;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class RackExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Kode', 'Zona'];
    }

    public function exportQuery(): Builder
    {
        return Rack::query()->orderBy('zone')->orderBy('code');
    }

    public function mapRow($model): array
    {
        return [$model->code, $model->zone];
    }
}
