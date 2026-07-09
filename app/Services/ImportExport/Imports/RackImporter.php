<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Rack;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class RackImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Rack::class;
    }

    public function uniqueKey(): string|array
    {
        return 'code';
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'zone' => ['required', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Kode', 'Zona'];
    }
}
