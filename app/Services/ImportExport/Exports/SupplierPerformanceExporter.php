<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Cycle;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class SupplierPerformanceExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Cycle #', 'Supplier', 'Jadwal', 'Waktu Terima', 'Item Lengkap', 'Total Item', 'Status'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return Cycle::query()
            ->with(['supplier', 'items'])
            ->where('status', 'completed')
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('received_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('received_at', '<=', $filters['date_to']))
            ->latest('received_at');
    }

    public function mapRow($model): array
    {
        $totalItems = $model->items->count();
        $completeItems = $model->items->filter(fn ($item) => $item->received_quantity >= $item->quantity)->count();
        $isFull = $totalItems > 0 && $completeItems === $totalItems;

        if (! $isFull) {
            $status = 'Kurang';
        } else {
            $status = $model->delivery_date && $model->received_at?->toDateString() <= $model->delivery_date?->format('Y-m-d')
                ? 'Tepat Waktu'
                : 'Terlambat';
        }

        return [
            $model->cycle_number,
            $model->supplier?->name ?? '-',
            $model->delivery_date?->format('Y-m-d') ?? '-',
            $model->received_at?->format('d/m/Y H:i:s') ?? '-',
            $completeItems,
            $totalItems,
            $status,
        ];
    }
}
