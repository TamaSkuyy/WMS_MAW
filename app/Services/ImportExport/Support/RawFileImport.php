<?php

namespace App\Services\ImportExport\Support;

use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class RawFileImport implements WithCustomCsvSettings
{
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
        ];
    }
}
