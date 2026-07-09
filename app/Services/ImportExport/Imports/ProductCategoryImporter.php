<?php

namespace App\Services\ImportExport\Imports;

use App\Models\ProductCategory;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class ProductCategoryImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return ProductCategory::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Deskripsi'];
    }
}
