<?php

namespace App\Services\ImportExport\Exports;

use App\Models\ProductCategory;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Deskripsi'];
    }

    public function exportQuery(): Builder
    {
        return ProductCategory::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->description];
    }
}
