<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Product;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ProductExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Part Number', 'Nama', 'Model Kendaraan', 'Supplier', 'Kategori', 'Satuan', 'Harga', 'Aktif'];
    }

    public function exportQuery(): Builder
    {
        return Product::query()->with(['vehicleModel', 'supplier', 'category'])->orderBy('part_number');
    }

    public function mapRow($model): array
    {
        return [
            $model->part_number,
            $model->name,
            trim(($model->vehicleModel->brand ?? '').' '.($model->vehicleModel->name ?? '')),
            $model->supplier->name ?? '-',
            $model->category->name ?? '-',
            $model->unit,
            $model->base_price,
            $model->is_active ? 'Ya' : 'Tidak',
        ];
    }
}
