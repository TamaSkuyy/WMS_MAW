<?php

namespace App\Services\ImportExport\Imports;

use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class VehicleModelImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return VehicleModel::class;
    }

    public function uniqueKey(): string|array
    {
        return ['name', 'brand'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Merek', 'Suffix'];
    }
}
