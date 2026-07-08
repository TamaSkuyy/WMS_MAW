<?php

namespace App\Services\ImportExport\Exports;

use App\Models\ShipmentItem;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ShipmentReportExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Tanggal', 'Partner', 'Part Number', 'Nama Produk', 'Rak', 'Qty', 'Status'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return ShipmentItem::query()
            ->with(['shipment', 'product', 'rack'])
            ->whereHas('shipment', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shipment_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shipment_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->where('partner_name', 'like', '%' . $filters['partner'] . '%');
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
            $model->shipment->shipment_date->format('Y-m-d'),
            $model->shipment->partner_name,
            $model->product->part_number,
            $model->product->name,
            $model->rack->code,
            $model->quantity,
            $model->shipment->status,
        ];
    }
}
