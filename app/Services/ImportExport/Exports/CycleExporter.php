<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Cycle;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class CycleExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Cycle Number', 'Supplier', 'Delivery Date', 'Total Items', 'Status', 'Notes'];
    }

    public function exportQuery(): Builder
    {
        return Cycle::query()->with('supplier')->withCount('items')->orderBy('created_at', 'desc');
    }

    public function mapRow($model): array
    {
        return [
            $model->cycle_number,
            $model->supplier?->name ?? '',
            $model->delivery_date?->format('Y-m-d') ?? '',
            $model->items_count ?? 0,
            $model->status,
            $model->notes ?? '',
        ];
    }
}
