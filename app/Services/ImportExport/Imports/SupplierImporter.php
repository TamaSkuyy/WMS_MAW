<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class SupplierImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Supplier::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Kode', 'Nama', 'Kontak', 'Email', 'Telepon', 'Jalan', 'Kota', 'Provinsi', 'KodePos', 'Negara'];
    }

    public function insertRow(array $data): void
    {
        $supplier = Supplier::create([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        $supplier->addresses()->create([
            'street' => $data['street'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'address_type' => 'primary',
        ]);
    }
}
