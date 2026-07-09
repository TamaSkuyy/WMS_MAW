<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class SupplierExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Kontak', 'Email', 'Telepon', 'Jalan', 'Kota', 'Provinsi', 'KodePos', 'Negara'];
    }

    public function exportQuery(): Builder
    {
        return Supplier::query()->with('primaryAddress')->orderBy('name');
    }

    public function mapRow($model): array
    {
        $address = $model->primaryAddress;

        return [
            $model->name,
            $model->contact_person,
            $model->email,
            $model->phone,
            $address->street ?? '-',
            $address->city ?? '-',
            $address->state ?? '-',
            $address->postal_code ?? '-',
            $address->country ?? '-',
        ];
    }
}
