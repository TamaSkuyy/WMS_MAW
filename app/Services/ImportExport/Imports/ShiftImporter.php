<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Shift;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class ShiftImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Shift::class;
    }

    public function uniqueKey(): string|array
    {
        return 'code';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'string', 'in:Aktif,Nonaktif'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Kode', 'Jam Mulai', 'Jam Selesai', 'Status'];
    }

    public function fixedFields(int $userId): array
    {
        return [
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    public function transformRow(array $mapped): array
    {
        $mapped['status'] = $mapped['status'] ?: 'Aktif';

        return $mapped;
    }
}
