<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class EmployeeImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Employee::class;
    }

    public function uniqueKey(): string|array
    {
        return 'nik';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'job_position_id' => ['nullable', 'exists:job_positions,id'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', 'in:Aktif,Nonaktif'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'NIK', 'Jabatan', 'Lokasi', 'Departemen', 'Telepon', 'Email', 'Status'];
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
        $mapped['job_position_id'] = $this->resolveForeignKey(JobPosition::class, 'name', $mapped['job_position'] ?? null, required: false);
        $mapped['work_location_id'] = $this->resolveForeignKey(WorkLocation::class, 'name', $mapped['work_location'] ?? null, required: false);
        $mapped['department_id'] = $this->resolveForeignKey(Department::class, 'name', $mapped['department'] ?? null, required: false);
        $mapped['status'] = $mapped['status'] ?: 'Aktif';

        unset($mapped['job_position'], $mapped['work_location'], $mapped['department']);

        return $mapped;
    }
}
