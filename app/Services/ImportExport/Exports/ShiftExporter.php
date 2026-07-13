<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Shift;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ShiftExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Kode', 'Jam Mulai', 'Jam Selesai', 'Status'];
    }

    public function exportQuery(): Builder
    {
        return Shift::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->code, $model->start_time, $model->end_time, $model->status];
    }
}
