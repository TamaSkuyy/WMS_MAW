# Suffix on Vehicle Model & Shipment Product Filter — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `suffix` field to `vehicle_models` to distinguish car variants (e.g., Fortuner VRZ vs SRZ), propagate suffix display across Product pages, and add a vehicle model filter on Shipment create/edit so selecting a model auto-filters the product list.

**Architecture:** Suffix lives on `vehicle_models` only — products already carry `vehicle_model_id` so differentiation flows naturally. Shipment filtering is client-side (all products already loaded); no new API endpoint needed. Display format everywhere: `{brand} {name} {suffix}` with suffix omitted if null.

**Tech Stack:** Laravel 11, Inertia.js, React/TSX, Spatie Permissions, PHPUnit (Feature tests), MySQL.

---

## File Map

| File | Action | Change |
|------|--------|--------|
| `database/migrations/2026_06_28_000001_add_suffix_to_vehicle_models_table.php` | Create | Add `suffix` column, update unique constraint |
| `database/factories/VehicleModelFactory.php` | Modify | Add `suffix` to factory definition |
| `app/Models/VehicleModel.php` | Modify | Add `suffix` to `$fillable` |
| `app/Http/Controllers/VehicleModelController.php` | Modify | Add `suffix` to validation rules |
| `resources/js/Pages/Master/VehicleModels/Index.tsx` | Modify | Add Suffix column |
| `resources/js/Pages/Master/VehicleModels/Create.tsx` | Modify | Add Suffix input field |
| `resources/js/Pages/Master/VehicleModels/Edit.tsx` | Modify | Add Suffix input field |
| `resources/js/Pages/Master/Products/Create.tsx` | Modify | Update vehicle model label to include suffix |
| `resources/js/Pages/Master/Products/Edit.tsx` | Modify | Update vehicle model label to include suffix |
| `resources/js/Pages/Master/Products/Index.tsx` | Modify | Update vehicle model cell to include suffix |
| `resources/js/Pages/Master/Products/Show.tsx` | Modify | Update vehicle model display to include suffix |
| `app/Http/Controllers/ShipmentController.php` | Modify | Pass `vehicleModels` to Create/Edit views |
| `resources/js/Pages/Transactions/Shipments/Create.tsx` | Modify | Add vehicle model filter that client-side filters products |
| `resources/js/Pages/Transactions/Shipments/Edit.tsx` | Modify | Add vehicle model filter that client-side filters products |
| `tests/Feature/VehicleModelControllerTest.php` | Create | Tests for suffix CRUD |
| `tests/Feature/ShipmentControllerTest.php` | Modify | Test vehicleModels passed to create view |

---

## Task 1: Migration — add suffix to vehicle_models

**Files:**
- Create: `database/migrations/2026_06_28_000001_add_suffix_to_vehicle_models_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->string('suffix', 50)->nullable()->after('name');
            $table->dropUnique(['name', 'brand']);
            $table->unique(['name', 'brand', 'suffix']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->dropUnique(['name', 'brand', 'suffix']);
            $table->dropColumn('suffix');
            $table->unique(['name', 'brand']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_06_28_000001_add_suffix_to_vehicle_models_table` → `Migrated`.

---

## Task 2: VehicleModel model + factory + controller

**Files:**
- Modify: `app/Models/VehicleModel.php`
- Modify: `database/factories/VehicleModelFactory.php`
- Modify: `app/Http/Controllers/VehicleModelController.php`

- [ ] **Step 1: Add `suffix` to VehicleModel `$fillable`**

In `app/Models/VehicleModel.php`, change:
```php
protected $fillable = [
    'name',
    'brand',
];
```
to:
```php
protected $fillable = [
    'name',
    'brand',
    'suffix',
];
```

- [ ] **Step 2: Update VehicleModelFactory to include suffix**

Replace the `definition()` method in `database/factories/VehicleModelFactory.php`:

```php
public function definition(): array
{
    return [
        'name'   => $this->faker->unique()->word(),
        'brand'  => $this->faker->randomElement(['Toyota', 'Honda', 'Mitsubishi', 'Daihatsu']),
        'suffix' => $this->faker->optional(0.6)->randomElement(['VRZ', 'SRZ', 'TRD', 'GR Sport', 'G', 'Cross']),
    ];
}
```

- [ ] **Step 3: Update VehicleModelController validation**

In `app/Http/Controllers/VehicleModelController.php`, update `store()`:

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name'   => 'required|string|max:100',
        'brand'  => 'required|string|max:100',
        'suffix' => 'nullable|string|max:50',
    ]);
    VehicleModel::create($validated);
    return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil dibuat.');
}
```

Update `update()`:

```php
public function update(Request $request, VehicleModel $vehicleModel)
{
    $validated = $request->validate([
        'name'   => [
            'required', 'string', 'max:100',
            \Illuminate\Validation\Rule::unique('vehicle_models')->where(function ($query) use ($request) {
                return $query->where('brand', $request->brand)
                             ->where('suffix', $request->suffix);
            })->ignore($vehicleModel->id),
        ],
        'brand'  => 'required|string|max:100',
        'suffix' => 'nullable|string|max:50',
    ]);
    $vehicleModel->update($validated);
    return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil diupdate.');
}
```

---

## Task 3: VehicleModel frontend — Index, Create, Edit

**Files:**
- Modify: `resources/js/Pages/Master/VehicleModels/Index.tsx`
- Modify: `resources/js/Pages/Master/VehicleModels/Create.tsx`
- Modify: `resources/js/Pages/Master/VehicleModels/Edit.tsx`

- [ ] **Step 1: Update Index.tsx — add Suffix column**

Replace the `<thead>` and `<tbody>` rows in `resources/js/Pages/Master/VehicleModels/Index.tsx`:

```tsx
<thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
    <tr>
        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Merek</th>
        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Model</th>
        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Suffix</th>
        <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
    </tr>
</thead>
<tbody>
    {vehicleModels.data.map((m: any) => (
        <tr key={m.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
            <td className="px-4 py-3 text-sm text-[#1A1D23]">{m.brand}</td>
            <td className="px-4 py-3 text-sm text-[#1A1D23]">{m.name}</td>
            <td className="px-4 py-3 text-sm text-[#6C757D]">{m.suffix || '-'}</td>
            <td className="px-4 py-3 text-sm text-[#1A1D23]">
                <TableActions
                    editRoute={route('vehicle-models.edit', m.id)}
                    onDelete={() => handleDelete(m.id)}
                />
            </td>
        </tr>
    ))}
</tbody>
```

- [ ] **Step 2: Update Create.tsx — add Suffix input**

Replace the entire file content of `resources/js/Pages/Master/VehicleModels/Create.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Create() {
    const { data, setData, post, errors } = useForm({ name: '', brand: '', suffix: '' });
    return (
        <AppLayout>
            <Head title="Tambah Model" />
            <PageBreadcrumb pageTitle="Tambah Model Kendaraan" />
            <ComponentCard title="Model Baru">
                <form onSubmit={(e) => { e.preventDefault(); post(route('vehicle-models.store')); }} className="space-y-4 max-w-md">
                    <div>
                        <Label>Merek *</Label>
                        <Input type="text" value={data.brand} onChange={(e) => setData('brand', e.target.value)} placeholder="contoh: Toyota" />
                        {errors.brand && <p className="mt-1 text-sm text-red-500">{errors.brand}</p>}
                    </div>
                    <div>
                        <Label>Model *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Fortuner" />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Suffix (Varian)</Label>
                        <Input type="text" value={data.suffix} onChange={(e) => setData('suffix', e.target.value)} placeholder="contoh: VRZ, TRD, SRZ (kosongkan jika tidak ada)" />
                        {errors.suffix && <p className="mt-1 text-sm text-red-500">{errors.suffix}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit">Simpan</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Update Edit.tsx — add Suffix input**

Replace the entire file content of `resources/js/Pages/Master/VehicleModels/Edit.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';

export default function Edit({ vehicleModel }: any) {
    const { data, setData, put, errors } = useForm({
        name: vehicleModel.name,
        brand: vehicleModel.brand,
        suffix: vehicleModel.suffix || '',
    });
    return (
        <AppLayout>
            <Head title={`Edit Model - ${vehicleModel.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${vehicleModel.brand} ${vehicleModel.name}${vehicleModel.suffix ? ' ' + vehicleModel.suffix : ''}`} />
            <ComponentCard title="Edit Model">
                <form onSubmit={(e) => { e.preventDefault(); put(route('vehicle-models.update', vehicleModel.id)); }} className="space-y-4 max-w-md">
                    <div>
                        <Label>Merek *</Label>
                        <Input type="text" value={data.brand} onChange={(e) => setData('brand', e.target.value)} />
                        {errors.brand && <p className="mt-1 text-sm text-red-500">{errors.brand}</p>}
                    </div>
                    <div>
                        <Label>Model *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label>Suffix (Varian)</Label>
                        <Input type="text" value={data.suffix} onChange={(e) => setData('suffix', e.target.value)} placeholder="contoh: VRZ, TRD, SRZ (kosongkan jika tidak ada)" />
                        {errors.suffix && <p className="mt-1 text-sm text-red-500">{errors.suffix}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit">Update</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
```

---

## Task 4: Product frontend — update vehicle model display labels

**Files:**
- Modify: `resources/js/Pages/Master/Products/Create.tsx`
- Modify: `resources/js/Pages/Master/Products/Edit.tsx`
- Modify: `resources/js/Pages/Master/Products/Index.tsx`
- Modify: `resources/js/Pages/Master/Products/Show.tsx`

A helper label builder is used in all 4 files:

```ts
const vmLabel = (m: any) => `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}`;
```

- [ ] **Step 1: Update Products/Create.tsx — vehicle model dropdown label**

In `resources/js/Pages/Master/Products/Create.tsx`, find the vehicle model SearchableSelect options and replace:

```tsx
options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.name} (${m.brand})` }))}
```
with:
```tsx
options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}` }))}
```

- [ ] **Step 2: Update Products/Edit.tsx — vehicle model dropdown label**

In `resources/js/Pages/Master/Products/Edit.tsx`, find the vehicle model SearchableSelect options and replace:

```tsx
options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.name} (${m.brand})` }))}
```
with:
```tsx
options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}` }))}
```

- [ ] **Step 3: Update Products/Index.tsx — vehicle model cell**

In `resources/js/Pages/Master/Products/Index.tsx`, find the vehicle model table cell and replace:

```tsx
<td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">{product.vehicle_model?.name || '-'}</td>
```
with:
```tsx
<td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
    {product.vehicle_model
        ? `${product.vehicle_model.brand} ${product.vehicle_model.name}${product.vehicle_model.suffix ? ' ' + product.vehicle_model.suffix : ''}`
        : '-'}
</td>
```

- [ ] **Step 4: Update Products/Show.tsx — vehicle model detail row**

In `resources/js/Pages/Master/Products/Show.tsx`, find the vehicle model `<dd>` and replace:

```tsx
<dd className="text-sm text-[#1A1D23]">{product.vehicle_model ? `${product.vehicle_model.name} (${product.vehicle_model.brand})` : '-'}</dd>
```
with:
```tsx
<dd className="text-sm text-[#1A1D23]">
    {product.vehicle_model
        ? `${product.vehicle_model.brand} ${product.vehicle_model.name}${product.vehicle_model.suffix ? ' ' + product.vehicle_model.suffix : ''}`
        : '-'}
</dd>
```

---

## Task 5: Shipment — pass vehicleModels + client-side filter

**Files:**
- Modify: `app/Http/Controllers/ShipmentController.php`
- Modify: `resources/js/Pages/Transactions/Shipments/Create.tsx`
- Modify: `resources/js/Pages/Transactions/Shipments/Edit.tsx`

- [ ] **Step 1: Update ShipmentController — pass vehicleModels to create() and edit()**

In `app/Http/Controllers/ShipmentController.php`, update `create()`:

```php
public function create()
{
    return Inertia::render('Transactions/Shipments/Create', [
        'products'      => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
        'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
        'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
    ]);
}
```

Update `edit()`:

```php
public function edit(Shipment $shipment)
{
    if ($shipment->status !== 'draft') {
        return back()->with('error', 'Only draft shipments can be edited.');
    }

    return Inertia::render('Transactions/Shipments/Edit', [
        'shipment'      => $shipment->load('items.product'),
        'products'      => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
        'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
        'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
    ]);
}
```

- [ ] **Step 2: Update Shipments/Create.tsx — add vehicle model filter**

Replace the entire file content of `resources/js/Pages/Transactions/Shipments/Create.tsx`:

```tsx
import React, { useState, useMemo } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import { Link } from '@inertiajs/react';

const vmLabel = (m: any) => `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}`;

export default function Create({ products, racks, vehicleModels }: any) {
    const { data, setData, post, errors } = useForm({
        partner_name: '',
        shipment_date: new Date().toISOString().split('T')[0],
        notes: '',
        items: [] as { product_id: string; rack_id: string; quantity: number }[],
    });

    const [selVehicleModel, setSelVehicleModel] = useState('');
    const [selProduct, setSelProduct] = useState('');
    const [selRack, setSelRack] = useState('');
    const [selQty, setSelQty] = useState(1);

    const filteredProducts = useMemo(() => {
        if (!selVehicleModel) return products;
        return products.filter((p: any) => String(p.vehicle_model_id) === String(selVehicleModel));
    }, [selVehicleModel, products]);

    const addItem = () => {
        if (!selProduct || !selRack || selQty < 1) return;
        setData('items', [...data.items, { product_id: selProduct, rack_id: selRack, quantity: selQty }]);
        setSelProduct('');
        setSelRack('');
        setSelQty(1);
    };

    const removeItem = (i: number) => setData('items', data.items.filter((_: any, idx: number) => idx !== i));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('shipments.store'));
    };

    return (
        <AppLayout>
            <Head title="Shipment Baru" />
            <PageBreadcrumb pageTitle="Tambah Shipment" />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <ComponentCard
                        title="Info Shipment"
                        desc="Data pengiriman barang"
                        action={
                            <Link href={route('shipments.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-5">
                            <div>
                                <Label>Nama Mitra *</Label>
                                <Input type="text" value={data.partner_name} onChange={(e) => setData('partner_name', e.target.value)} placeholder="contoh: PT Maju Jaya" />
                                {errors.partner_name && <p className="mt-1 text-sm text-red-500">{errors.partner_name}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim *</Label>
                                <Input type="date" value={data.shipment_date} onChange={(e) => setData('shipment_date', e.target.value)} />
                                {errors.shipment_date && <p className="mt-1 text-sm text-red-500">{errors.shipment_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm" />
                            </div>
                        </div>
                    </ComponentCard>

                    <ComponentCard title="Tambah Item" desc="Pilih produk yang akan dikirim">
                        <div className="mb-4">
                            <Label>Filter Model Kendaraan</Label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'Semua Model' },
                                    ...vehicleModels.map((m: any) => ({ value: m.id, label: vmLabel(m) })),
                                ]}
                                value={selVehicleModel}
                                onChange={(v) => { setSelVehicleModel(v as string); setSelProduct(''); }}
                            />
                        </div>

                        <div className="flex gap-2 items-end mb-4 flex-wrap">
                            <div className="flex-1 min-w-[150px]">
                                <Label>Produk</Label>
                                <SearchableSelect
                                    options={filteredProducts.map((p: any) => ({ value: p.id, label: `${p.part_number} - ${p.name}` }))}
                                    value={selProduct}
                                    onChange={(v) => setSelProduct(v as string)}
                                />
                            </div>
                            <div className="w-24">
                                <Label>Rak</Label>
                                <SearchableSelect
                                    options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
                                    value={selRack}
                                    onChange={(v) => setSelRack(v as string)}
                                />
                            </div>
                            <div className="w-20">
                                <Label>Qty</Label>
                                <Input type="number" value={selQty} onChange={(e) => setSelQty(parseInt(e.target.value) || 0)} min={1} />
                            </div>
                            <Button type="button" onClick={addItem}>Tambah</Button>
                        </div>
                        {errors.items && <p className="mt-1 text-sm text-red-500">{errors.items}</p>}

                        {data.items.length > 0 && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {data.items.map((item: any, i: number) => {
                                        const p = products.find((pr: any) => String(pr.id) === String(item.product_id));
                                        const r = racks.find((rk: any) => String(rk.id) === String(item.rack_id));
                                        return (
                                            <tr key={i}>
                                                <td className="px-3 py-2 text-sm">{p?.name || '-'}</td>
                                                <td className="px-3 py-2 text-sm font-mono">{r?.code || '-'}</td>
                                                <td className="px-3 py-2 text-sm">{item.quantity}</td>
                                                <td className="px-3 py-2"><button type="button" onClick={() => removeItem(i)} className="text-red-500 text-sm">&#x2715;</button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </ComponentCard>
                </div>
                <div className="mt-4 flex gap-3">
                    <Button type="submit" disabled={data.items.length === 0} icon={<CheckIcon className="w-4 h-4" />}>Simpan Shipment</Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Update Shipments/Edit.tsx — add vehicle model filter**

Read the current `resources/js/Pages/Transactions/Shipments/Edit.tsx` file, then add the same vehicle model filter pattern as Create. The key additions are:

1. Add `vehicleModels` to the component props.
2. Add `import { useMemo } from 'react'` if not already imported.
3. Add `selVehicleModel` state and `filteredProducts` memo (same as Create).
4. Add the `vmLabel` helper at top of file.
5. Add the "Filter Model Kendaraan" SearchableSelect above the product picker, resetting `selProduct` when vehicle model changes.

Full replacement of `resources/js/Pages/Transactions/Shipments/Edit.tsx` (read existing file first to get current item init logic, then apply the pattern):

```tsx
import React, { useState, useMemo } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import { Link } from '@inertiajs/react';

const vmLabel = (m: any) => `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}`;

export default function Edit({ shipment, products, racks, vehicleModels }: any) {
    const { data, setData, put, errors } = useForm({
        partner_name: shipment.partner_name || '',
        shipment_date: shipment.shipment_date || '',
        notes: shipment.notes || '',
        items: (shipment.items || []).map((item: any) => ({
            product_id: String(item.product_id),
            rack_id: String(item.rack_id),
            quantity: item.quantity,
        })),
    });

    const [selVehicleModel, setSelVehicleModel] = useState('');
    const [selProduct, setSelProduct] = useState('');
    const [selRack, setSelRack] = useState('');
    const [selQty, setSelQty] = useState(1);

    const filteredProducts = useMemo(() => {
        if (!selVehicleModel) return products;
        return products.filter((p: any) => String(p.vehicle_model_id) === String(selVehicleModel));
    }, [selVehicleModel, products]);

    const addItem = () => {
        if (!selProduct || !selRack || selQty < 1) return;
        setData('items', [...data.items, { product_id: selProduct, rack_id: selRack, quantity: selQty }]);
        setSelProduct('');
        setSelRack('');
        setSelQty(1);
    };

    const removeItem = (i: number) => setData('items', data.items.filter((_: any, idx: number) => idx !== i));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('shipments.update', shipment.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit Shipment #${shipment.id}`} />
            <PageBreadcrumb pageTitle={`Edit Shipment #${shipment.id}`} />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <ComponentCard
                        title="Info Shipment"
                        desc="Ubah data pengiriman"
                        action={
                            <Link href={route('shipments.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-5">
                            <div>
                                <Label>Nama Mitra *</Label>
                                <Input type="text" value={data.partner_name} onChange={(e) => setData('partner_name', e.target.value)} />
                                {errors.partner_name && <p className="mt-1 text-sm text-red-500">{errors.partner_name}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim *</Label>
                                <Input type="date" value={data.shipment_date} onChange={(e) => setData('shipment_date', e.target.value)} />
                                {errors.shipment_date && <p className="mt-1 text-sm text-red-500">{errors.shipment_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm" />
                            </div>
                        </div>
                    </ComponentCard>

                    <ComponentCard title="Edit Item" desc="Perbarui produk yang akan dikirim">
                        <div className="mb-4">
                            <Label>Filter Model Kendaraan</Label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'Semua Model' },
                                    ...vehicleModels.map((m: any) => ({ value: m.id, label: vmLabel(m) })),
                                ]}
                                value={selVehicleModel}
                                onChange={(v) => { setSelVehicleModel(v as string); setSelProduct(''); }}
                            />
                        </div>

                        <div className="flex gap-2 items-end mb-4 flex-wrap">
                            <div className="flex-1 min-w-[150px]">
                                <Label>Produk</Label>
                                <SearchableSelect
                                    options={filteredProducts.map((p: any) => ({ value: p.id, label: `${p.part_number} - ${p.name}` }))}
                                    value={selProduct}
                                    onChange={(v) => setSelProduct(v as string)}
                                />
                            </div>
                            <div className="w-24">
                                <Label>Rak</Label>
                                <SearchableSelect
                                    options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
                                    value={selRack}
                                    onChange={(v) => setSelRack(v as string)}
                                />
                            </div>
                            <div className="w-20">
                                <Label>Qty</Label>
                                <Input type="number" value={selQty} onChange={(e) => setSelQty(parseInt(e.target.value) || 0)} min={1} />
                            </div>
                            <Button type="button" onClick={addItem}>Tambah</Button>
                        </div>
                        {errors.items && <p className="mt-1 text-sm text-red-500">{errors.items}</p>}

                        {data.items.length > 0 && (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {data.items.map((item: any, i: number) => {
                                        const p = products.find((pr: any) => String(pr.id) === String(item.product_id));
                                        const r = racks.find((rk: any) => String(rk.id) === String(item.rack_id));
                                        return (
                                            <tr key={i}>
                                                <td className="px-3 py-2 text-sm">{p?.name || '-'}</td>
                                                <td className="px-3 py-2 text-sm font-mono">{r?.code || '-'}</td>
                                                <td className="px-3 py-2 text-sm">{item.quantity}</td>
                                                <td className="px-3 py-2"><button type="button" onClick={() => removeItem(i)} className="text-red-500 text-sm">&#x2715;</button></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        )}
                    </ComponentCard>
                </div>
                <div className="mt-4 flex gap-3">
                    <Button type="submit" disabled={data.items.length === 0} icon={<CheckIcon className="w-4 h-4" />}>Update Shipment</Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>
        </AppLayout>
    );
}
```

---

## Task 6: Tests — VehicleModel suffix

**Files:**
- Create: `tests/Feature/VehicleModelControllerTest.php`

- [ ] **Step 1: Create test file**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_vehicle_models(): void
    {
        VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->get(route('vehicle-models.index'));

        $response->assertStatus(200);
        $response->assertSee('Fortuner');
        $response->assertSee('Toyota');
    }

    public function test_store_creates_vehicle_model_with_suffix(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'VRZ',
        ]);

        $this->assertDatabaseHas('vehicle_models', [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'VRZ',
        ]);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_store_creates_vehicle_model_without_suffix(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'  => 'Hilux',
            'brand' => 'Toyota',
        ]);

        $this->assertDatabaseHas('vehicle_models', [
            'name'   => 'Hilux',
            'brand'  => 'Toyota',
            'suffix' => null,
        ]);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_same_name_brand_different_suffix_allowed(): void
    {
        VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'SRZ',
        ]);

        $this->assertDatabaseCount('vehicle_models', 2);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_update_modifies_suffix(): void
    {
        $model = VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->put(route('vehicle-models.update', $model), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'TRD',
        ]);

        $this->assertDatabaseHas('vehicle_models', ['id' => $model->id, 'suffix' => 'TRD']);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_destroy_deletes_vehicle_model(): void
    {
        $model = VehicleModel::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('vehicle-models.destroy', $model));

        $this->assertDatabaseMissing('vehicle_models', ['id' => $model->id]);
        $response->assertRedirect(route('vehicle-models.index'));
    }
}
```

- [ ] **Step 2: Run the tests**

```bash
php artisan test tests/Feature/VehicleModelControllerTest.php --testdox
```

Expected: All 6 tests pass.

- [ ] **Step 3: Run full test suite to check for regressions**

```bash
php artisan test --testdox
```

Expected: All existing tests still pass (ProductControllerTest, ShipmentControllerTest, etc.).

---

## Task 7: Test — Shipment create passes vehicleModels

**Files:**
- Modify: `tests/Feature/ShipmentControllerTest.php`

- [ ] **Step 1: Add test for vehicleModels in Shipment create view**

Add this test method inside the `ShipmentControllerTest` class:

```php
public function test_create_passes_vehicle_models_to_view(): void
{
    \App\Models\VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

    $response = $this->actingAs($this->user)->get(route('shipments.create'));

    $response->assertStatus(200);
    $response->assertSee('Fortuner');
}
```

- [ ] **Step 2: Run updated ShipmentControllerTest**

```bash
php artisan test tests/Feature/ShipmentControllerTest.php --testdox
```

Expected: All tests pass including the new one.
