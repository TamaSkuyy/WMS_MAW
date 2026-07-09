<?php

namespace App\Services\ImportExport\Imports;

use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class WorkLocationImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return WorkLocation::class;
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
