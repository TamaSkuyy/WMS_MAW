<?php

namespace App\Services\ImportExport\Imports;

use App\Models\JobPosition;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class JobPositionImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return JobPosition::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Level'];
    }
}
