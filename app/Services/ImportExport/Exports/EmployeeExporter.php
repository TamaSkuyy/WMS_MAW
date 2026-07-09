<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Employee;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class EmployeeExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'NIK', 'Jabatan', 'Lokasi', 'Departemen', 'Telepon', 'Email', 'Status'];
    }

    public function exportQuery(): Builder
    {
        return Employee::query()->with(['jobPosition', 'workLocation', 'department'])->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [
            $model->name,
            $model->nik,
            $model->jobPosition->name ?? '-',
            $model->workLocation->name ?? '-',
            $model->department->name ?? '-',
            $model->phone,
            $model->email,
            $model->status,
        ];
    }
}
