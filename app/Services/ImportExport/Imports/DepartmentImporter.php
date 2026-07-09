<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Department;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class DepartmentImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Department::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama'];
    }
}
