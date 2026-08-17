<?php

namespace App\Services\ImportExport\Exports;

use App\Models\ShoppingItem;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ShoppingReportExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Tanggal Kirim', 'Partner', 'Frame #', 'Part Number', 'Nama Produk', 'Rak', 'Qty', 'Status', 'Dikirim Oleh', 'Waktu Kirim'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return ShoppingItem::query()
            ->with(['shopping.shoppingLocation', 'shopping.shippedBy', 'product', 'rack'])
            ->whereHas('shopping', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shopping_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shopping_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->whereHas('shoppingLocation', fn ($ql) => $ql->where('name', 'like', '%' . $filters['partner'] . '%'));
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
            $model->shopping->shopping_date->format('Y-m-d'),
            $model->shopping->shoppingLocation?->name ?? '-',
            $model->shopping->frame_number ?? '-',
            $model->product->part_number,
            $model->product->name,
            $model->rack?->code ?? '-',
            $model->quantity,
            $model->shopping->status,
            $model->shopping->shippedBy?->name ?? '-',
            $model->shopping->shipped_at ? $model->shopping->shipped_at->format('Y-m-d H:i:s') : '-',
        ];
    }
}
