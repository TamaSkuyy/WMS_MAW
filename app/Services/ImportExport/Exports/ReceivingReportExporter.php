<?php

namespace App\Services\ImportExport\Exports;

use App\Models\CycleItem;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ReceivingReportExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Tanggal', 'No. Cycle', 'Supplier', 'Part Number', 'Nama Produk', 'Rak', 'Qty Diterima', 'Status'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return CycleItem::query()
            ->with(['cycle.supplier', 'product', 'rack'])
            ->whereHas('cycle', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('received_at', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('received_at', '<=', $filters['date_to']);
                }
                if (! empty($filters['supplier_id'])) {
                    $q->where('supplier_id', $filters['supplier_id']);
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    public function mapRow($model): array
    {
        return [
            $model->cycle->received_at?->format('Y-m-d') ?? '-',
            $model->cycle->cycle_number,
            $model->cycle->supplier->name ?? '-',
            $model->product->part_number,
            $model->product->name,
            $model->rack->code ?? '-',
            $model->received_quantity,
            $model->cycle->status,
        ];
    }
}
