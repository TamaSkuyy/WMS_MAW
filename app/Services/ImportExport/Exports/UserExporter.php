<?php

namespace App\Services\ImportExport\Exports;

use App\Models\User;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class UserExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Name', 'Email', 'Role', 'Created At'];
    }

    public function exportQuery(): Builder
    {
        return User::with('roles');
    }

    public function mapRow($model): array
    {
        return [
            $model->name,
            $model->email,
            $model->roles->pluck('name')->implode(', '),
            $model->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
