# Shopping Unit + Suffix Trigger Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the manual one-by-one item input in Shipments Create/Edit with a Unit+Suffix trigger that auto-populates a product table, with per-row qty input and QR scan support.

**Architecture:** `ShipmentController` adds `stocks` eager load to `create()` and `edit()`. Both `Create.tsx` and `Edit.tsx` are rewritten to manage item state via `tableItems` local state, derived from vehicle model selection. Submit uses `router.post/put()` directly (items built at submit time). `QrScanner.tsx` from Quick Receive is reused without changes.

**Tech Stack:** React 18, TypeScript, Inertia.js v2, Laravel 11, QrScanner component (already exists at `resources/js/Components/QrScanner.tsx`).

---

## File Map

| File | Change |
|------|--------|
| `app/Http/Controllers/ShipmentController.php` | Add `stocks` eager load to `create()` and `edit()` |
| `resources/js/Pages/Transactions/Shipments/Create.tsx` | Full rewrite — Unit+Suffix trigger + table + QR scan |
| `resources/js/Pages/Transactions/Shipments/Edit.tsx` | Full rewrite — same + reconcile existing items on load |
| `tests/Feature/ShipmentControllerTest.php` | 2 new tests |

---

## Task 1: Backend — Eager Load Stocks (TDD)

**Files:**
- Modify: `tests/Feature/ShipmentControllerTest.php`
- Modify: `app/Http/Controllers/ShipmentController.php`

- [ ] **Step 1: Write the failing tests**

Add these 2 tests to `tests/Feature/ShipmentControllerTest.php`. The existing imports already cover `Product`, `Rack`, `Stock`, `VehicleModel` — add `VehicleModel` if not present:

```php
use App\Models\VehicleModel;
```

Then add the tests before the closing `}`:

```php
public function test_create_passes_products_with_stocks_to_view(): void
{
    $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
    $rack = Rack::factory()->create();
    $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
    Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 7]);

    $response = $this->actingAs($this->user)->get(route('shipments.create'));

    $response->assertStatus(200);
    $response->assertSee($product->part_number);
}

public function test_edit_passes_products_with_stocks_to_view(): void
{
    $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
    $rack = Rack::factory()->create();
    $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
    Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);
    $shipment = Shipment::factory()->create(['status' => 'draft']);
    $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

    $response = $this->actingAs($this->user)->get(route('shipments.edit', $shipment));

    $response->assertStatus(200);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter="test_create_passes_products_with_stocks|test_edit_passes_products_with_stocks"
```

Expected: FAIL or ERROR (DB connection down) — confirms tests are wired before implementation.

- [ ] **Step 3: Update ShipmentController::create() and edit()**

In `app/Http/Controllers/ShipmentController.php`, change the `products` line in both `create()` and `edit()` from:

```php
'products' => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
```

to:

```php
'products' => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
```

The `create()` method should become:

```php
public function create()
{
    return Inertia::render('Transactions/Shipments/Create', [
        'products'      => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
        'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
        'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
    ]);
}
```

The `edit()` method should become:

```php
public function edit(Shipment $shipment)
{
    if ($shipment->status !== 'draft') {
        return back()->with('error', 'Only draft shipments can be edited.');
    }
    return Inertia::render('Transactions/Shipments/Edit', [
        'shipment'      => $shipment->load('items.product.vehicleModel'),
        'products'      => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
        'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
        'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
    ]);
}
```

Note: `store()` and `update()` are unchanged — they already accept `items[].{product_id, rack_id, quantity}`.

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --filter="test_create_passes_products_with_stocks|test_edit_passes_products_with_stocks"
```

Expected: PASS (requires Docker MySQL to be running).

---

## Task 2: Create.tsx — Rewrite

**Files:**
- Modify: `resources/js/Pages/Transactions/Shipments/Create.tsx`

- [ ] **Step 1: Replace the entire file**

Overwrite `resources/js/Pages/Transactions/Shipments/Create.tsx` with:

```tsx
import React, { useState, useMemo, useEffect, useCallback } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import QrScanner from '../../../Components/QrScanner';

interface TableItem {
    product_id: number;
    part_number: string;
    name: string;
    stock: number;
    rack_id: string;
    quantity: number;
}

function beep() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        osc.connect(ctx.destination);
        osc.frequency.value = 880;
        osc.start();
        osc.stop(ctx.currentTime + 0.06);
    } catch (_) {}
}

export default function Create({ products, racks, vehicleModels }: any) {
    const pageErrors = ((usePage().props as any).errors ?? {}) as Record<string, string>;

    const [partnerName, setPartnerName] = useState('');
    const [shipmentDate, setShipmentDate] = useState(new Date().toISOString().split('T')[0]);
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const [selUnit, setSelUnit] = useState('');
    const [selSuffix, setSelSuffix] = useState('');
    const [tableItems, setTableItems] = useState<TableItem[]>([]);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | 'no_stock' | null>(null);

    const units = useMemo(() =>
        [...new Map(vehicleModels.map((m: any) => [`${m.brand} ${m.name}`, m])).values()]
            .map((m: any) => ({ value: `${m.brand} ${m.name}`, label: `${m.brand} ${m.name}` })),
        [vehicleModels]
    );

    const suffixes = useMemo(() =>
        vehicleModels
            .filter((m: any) => `${m.brand} ${m.name}` === selUnit)
            .map((m: any) => ({ value: m.suffix ?? '', label: m.suffix || 'Standar' })),
        [selUnit, vehicleModels]
    );

    const activeVehicleModelId = useMemo(() =>
        vehicleModels.find((m: any) =>
            `${m.brand} ${m.name}` === selUnit && (m.suffix ?? '') === selSuffix
        )?.id,
        [selUnit, selSuffix, vehicleModels]
    );

    useEffect(() => {
        if (!activeVehicleModelId) { setTableItems([]); return; }
        const filtered = products.filter((p: any) => p.vehicle_model_id === activeVehicleModelId);
        setTableItems(filtered.map((p: any) => ({
            product_id: p.id,
            part_number: p.part_number,
            name: p.name,
            stock: p.default_rack_id
                ? (p.stocks.find((s: any) => s.rack_id === p.default_rack_id)?.quantity ?? 0)
                : p.stocks.reduce((sum: number, s: any) => sum + s.quantity, 0),
            rack_id: p.default_rack_id ? String(p.default_rack_id) : '',
            quantity: 0,
        })));
    }, [activeVehicleModelId]);

    const handleScan = useCallback((code: string) => {
        const item = tableItems.find(i => i.part_number.toLowerCase() === code.toLowerCase());
        if (!item) { setLastScan(code); setLastScanStatus('unknown'); return; }
        if (item.stock <= 0) { setLastScan(code); setLastScanStatus('no_stock'); return; }
        beep();
        setLastScan(code);
        setLastScanStatus('ok');
        setTableItems(prev => prev.map(i =>
            i.product_id === item.product_id ? { ...i, quantity: i.quantity + 1 } : i
        ));
    }, [tableItems]);

    const updateItem = (productId: number, field: keyof TableItem, value: any) => {
        setTableItems(prev => prev.map(i =>
            i.product_id === productId ? { ...i, [field]: value } : i
        ));
    };

    const hasActiveItems = tableItems.some(i => i.quantity > 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!hasActiveItems || submitting) return;
        setSubmitting(true);
        router.post(route('shipments.store'), {
            partner_name: partnerName,
            shipment_date: shipmentDate,
            notes,
            items: tableItems
                .filter(i => i.quantity > 0)
                .map(i => ({ product_id: i.product_id, rack_id: i.rack_id, quantity: i.quantity })),
        }, { onFinish: () => setSubmitting(false) });
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
                                <Input type="text" value={partnerName} onChange={(e) => setPartnerName(e.target.value)} placeholder="contoh: PT Maju Jaya" />
                                {pageErrors.partner_name && <p className="mt-1 text-sm text-red-500">{pageErrors.partner_name}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim *</Label>
                                <Input type="date" value={shipmentDate} onChange={(e) => setShipmentDate(e.target.value)} />
                                {pageErrors.shipment_date && <p className="mt-1 text-sm text-red-500">{pageErrors.shipment_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="pt-3 border-t border-gray-100 space-y-4">
                                <div>
                                    <Label>Unit *</Label>
                                    <SearchableSelect
                                        options={units}
                                        value={selUnit}
                                        onChange={(v) => { setSelUnit(v as string); setSelSuffix(''); }}
                                    />
                                </div>
                                <div>
                                    <Label>Suffix *</Label>
                                    <SearchableSelect
                                        options={selUnit ? suffixes : []}
                                        value={selSuffix}
                                        onChange={(v) => setSelSuffix(v as string)}
                                    />
                                    {!selUnit && <p className="mt-1 text-xs text-gray-400">Pilih Unit terlebih dahulu</p>}
                                </div>
                            </div>
                        </div>
                    </ComponentCard>

                    <ComponentCard title="Daftar Part" desc="Muncul otomatis sesuai Unit + Suffix">
                        <div className="mb-3 flex items-center justify-between flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setScannerOpen(true)}
                                disabled={!activeVehicleModelId}
                            >
                                📷 Scan QR
                            </Button>
                            {lastScan && (
                                <span className={`text-xs px-2 py-1 rounded ${
                                    lastScanStatus === 'ok'
                                        ? 'bg-green-50 text-green-700'
                                        : lastScanStatus === 'no_stock'
                                        ? 'bg-yellow-50 text-yellow-700'
                                        : 'bg-orange-50 text-orange-700'
                                }`}>
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}
                                    {lastScan}
                                    {lastScanStatus === 'unknown' && ' — tidak dikenal'}
                                    {lastScanStatus === 'no_stock' && ' — stok 0'}
                                </span>
                            )}
                        </div>

                        {tableItems.length === 0 ? (
                            <p className="text-sm text-gray-400 py-8 text-center">
                                {activeVehicleModelId
                                    ? 'Tidak ada part untuk kombinasi ini.'
                                    : 'Pilih Unit & Suffix untuk memuat part.'}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {tableItems.map((item) => (
                                            <tr
                                                key={item.product_id}
                                                className={item.stock === 0 ? 'bg-red-50 dark:bg-red-900/10 opacity-60' : ''}
                                            >
                                                <td className="px-3 py-2 text-xs font-mono whitespace-nowrap">{item.part_number}</td>
                                                <td className="px-3 py-2 text-sm">{item.name}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                        item.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                    }`}>
                                                        {item.stock}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 w-32">
                                                    <SearchableSelect
                                                        options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
                                                        value={item.rack_id}
                                                        onChange={(v) => updateItem(item.product_id, 'rack_id', v as string)}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 w-20">
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(item.product_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        min={0}
                                                        max={item.stock}
                                                        disabled={item.stock === 0}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {pageErrors.items && <p className="mt-2 text-sm text-red-500">{pageErrors.items}</p>}
                    </ComponentCard>
                </div>

                <div className="mt-4 flex gap-3">
                    <Button
                        type="submit"
                        disabled={!hasActiveItems || submitting}
                        icon={<CheckIcon className="w-4 h-4" />}
                    >
                        {submitting ? 'Menyimpan...' : 'Simpan Shipment'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
            />
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify TypeScript compiles**

```bash
npx tsc --noEmit 2>&1 | grep -i "Shipments/Create"
```

Expected: no errors for Create.tsx.

---

## Task 3: Edit.tsx — Rewrite + Reconcile

**Files:**
- Modify: `resources/js/Pages/Transactions/Shipments/Edit.tsx`

- [ ] **Step 1: Replace the entire file**

Overwrite `resources/js/Pages/Transactions/Shipments/Edit.tsx` with:

```tsx
import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import QrScanner from '../../../Components/QrScanner';

interface TableItem {
    product_id: number;
    part_number: string;
    name: string;
    stock: number;
    rack_id: string;
    quantity: number;
}

function beep() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        osc.connect(ctx.destination);
        osc.frequency.value = 880;
        osc.start();
        osc.stop(ctx.currentTime + 0.06);
    } catch (_) {}
}

export default function Edit({ shipment, products, racks, vehicleModels }: any) {
    const pageErrors = ((usePage().props as any).errors ?? {}) as Record<string, string>;

    const [partnerName, setPartnerName] = useState(shipment.partner_name || '');
    const [shipmentDate, setShipmentDate] = useState(shipment.shipment_date || '');
    const [notes, setNotes] = useState(shipment.notes || '');
    const [submitting, setSubmitting] = useState(false);

    const [selUnit, setSelUnit] = useState('');
    const [selSuffix, setSelSuffix] = useState('');
    const [tableItems, setTableItems] = useState<TableItem[]>([]);
    const [scannerOpen, setScannerOpen] = useState(false);
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | 'no_stock' | null>(null);

    // Map of saved items: product_id → { qty, rack_id }
    // Built once from shipment.items; used only on first table populate
    const savedItemsMap = useMemo<Record<number, { qty: number; rack_id: string }>>(() =>
        Object.fromEntries(
            (shipment.items ?? []).map((i: any) => [i.product_id, { qty: i.quantity, rack_id: String(i.rack_id) }])
        ),
        [] // intentionally no deps — only built from initial prop value
    );
    const isFirstPopulate = useRef(true);

    // On mount: pre-select Unit + Suffix from first existing item
    useEffect(() => {
        if (!shipment.items?.length) return;
        const firstItem = shipment.items[0];
        const prod = products.find((p: any) => p.id === firstItem.product_id);
        if (!prod?.vehicle_model) return;
        const vm = prod.vehicle_model;
        setSelUnit(`${vm.brand} ${vm.name}`);
        setSelSuffix(vm.suffix ?? '');
    }, []);

    const units = useMemo(() =>
        [...new Map(vehicleModels.map((m: any) => [`${m.brand} ${m.name}`, m])).values()]
            .map((m: any) => ({ value: `${m.brand} ${m.name}`, label: `${m.brand} ${m.name}` })),
        [vehicleModels]
    );

    const suffixes = useMemo(() =>
        vehicleModels
            .filter((m: any) => `${m.brand} ${m.name}` === selUnit)
            .map((m: any) => ({ value: m.suffix ?? '', label: m.suffix || 'Standar' })),
        [selUnit, vehicleModels]
    );

    const activeVehicleModelId = useMemo(() =>
        vehicleModels.find((m: any) =>
            `${m.brand} ${m.name}` === selUnit && (m.suffix ?? '') === selSuffix
        )?.id,
        [selUnit, selSuffix, vehicleModels]
    );

    useEffect(() => {
        if (!activeVehicleModelId) { setTableItems([]); return; }
        const filtered = products.filter((p: any) => p.vehicle_model_id === activeVehicleModelId);
        const useMap = isFirstPopulate.current ? savedItemsMap : {};
        if (isFirstPopulate.current) isFirstPopulate.current = false;
        setTableItems(filtered.map((p: any) => ({
            product_id: p.id,
            part_number: p.part_number,
            name: p.name,
            stock: p.default_rack_id
                ? (p.stocks.find((s: any) => s.rack_id === p.default_rack_id)?.quantity ?? 0)
                : p.stocks.reduce((sum: number, s: any) => sum + s.quantity, 0),
            rack_id: (useMap as any)[p.id]?.rack_id ?? (p.default_rack_id ? String(p.default_rack_id) : ''),
            quantity: (useMap as any)[p.id]?.qty ?? 0,
        })));
    }, [activeVehicleModelId]);

    const handleScan = useCallback((code: string) => {
        const item = tableItems.find(i => i.part_number.toLowerCase() === code.toLowerCase());
        if (!item) { setLastScan(code); setLastScanStatus('unknown'); return; }
        if (item.stock <= 0) { setLastScan(code); setLastScanStatus('no_stock'); return; }
        beep();
        setLastScan(code);
        setLastScanStatus('ok');
        setTableItems(prev => prev.map(i =>
            i.product_id === item.product_id ? { ...i, quantity: i.quantity + 1 } : i
        ));
    }, [tableItems]);

    const updateItem = (productId: number, field: keyof TableItem, value: any) => {
        setTableItems(prev => prev.map(i =>
            i.product_id === productId ? { ...i, [field]: value } : i
        ));
    };

    const hasActiveItems = tableItems.some(i => i.quantity > 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!hasActiveItems || submitting) return;
        setSubmitting(true);
        router.put(route('shipments.update', shipment.id), {
            partner_name: partnerName,
            shipment_date: shipmentDate,
            notes,
            items: tableItems
                .filter(i => i.quantity > 0)
                .map(i => ({ product_id: i.product_id, rack_id: i.rack_id, quantity: i.quantity })),
        }, { onFinish: () => setSubmitting(false) });
    };

    return (
        <AppLayout>
            <Head title="Edit Shipment" />
            <PageBreadcrumb pageTitle={`Edit: ${shipment.partner_name}`} />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <ComponentCard
                        title="Info Shipment"
                        desc="Perbarui data pengiriman"
                        action={
                            <Link href={route('shipments.index')}>
                                <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                            </Link>
                        }
                    >
                        <div className="space-y-5">
                            <div>
                                <Label>Nama Mitra *</Label>
                                <Input type="text" value={partnerName} onChange={(e) => setPartnerName(e.target.value)} />
                                {pageErrors.partner_name && <p className="mt-1 text-sm text-red-500">{pageErrors.partner_name}</p>}
                            </div>
                            <div>
                                <Label>Tanggal Kirim *</Label>
                                <Input type="date" value={shipmentDate} onChange={(e) => setShipmentDate(e.target.value)} />
                                {pageErrors.shipment_date && <p className="mt-1 text-sm text-red-500">{pageErrors.shipment_date}</p>}
                            </div>
                            <div>
                                <Label>Catatan</Label>
                                <textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="pt-3 border-t border-gray-100 space-y-4">
                                <div>
                                    <Label>Unit *</Label>
                                    <SearchableSelect
                                        options={units}
                                        value={selUnit}
                                        onChange={(v) => { setSelUnit(v as string); setSelSuffix(''); }}
                                    />
                                </div>
                                <div>
                                    <Label>Suffix *</Label>
                                    <SearchableSelect
                                        options={selUnit ? suffixes : []}
                                        value={selSuffix}
                                        onChange={(v) => setSelSuffix(v as string)}
                                    />
                                    {!selUnit && <p className="mt-1 text-xs text-gray-400">Pilih Unit terlebih dahulu</p>}
                                </div>
                            </div>
                        </div>
                    </ComponentCard>

                    <ComponentCard title="Daftar Part" desc="Muncul otomatis sesuai Unit + Suffix">
                        <div className="mb-3 flex items-center justify-between flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setScannerOpen(true)}
                                disabled={!activeVehicleModelId}
                            >
                                📷 Scan QR
                            </Button>
                            {lastScan && (
                                <span className={`text-xs px-2 py-1 rounded ${
                                    lastScanStatus === 'ok'
                                        ? 'bg-green-50 text-green-700'
                                        : lastScanStatus === 'no_stock'
                                        ? 'bg-yellow-50 text-yellow-700'
                                        : 'bg-orange-50 text-orange-700'
                                }`}>
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}
                                    {lastScan}
                                    {lastScanStatus === 'unknown' && ' — tidak dikenal'}
                                    {lastScanStatus === 'no_stock' && ' — stok 0'}
                                </span>
                            )}
                        </div>

                        {tableItems.length === 0 ? (
                            <p className="text-sm text-gray-400 py-8 text-center">
                                {activeVehicleModelId
                                    ? 'Tidak ada part untuk kombinasi ini.'
                                    : 'Pilih Unit & Suffix untuk memuat part.'}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rack</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {tableItems.map((item) => (
                                            <tr
                                                key={item.product_id}
                                                className={item.stock === 0 ? 'bg-red-50 dark:bg-red-900/10 opacity-60' : ''}
                                            >
                                                <td className="px-3 py-2 text-xs font-mono whitespace-nowrap">{item.part_number}</td>
                                                <td className="px-3 py-2 text-sm">{item.name}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                                                        item.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                    }`}>
                                                        {item.stock}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 w-32">
                                                    <SearchableSelect
                                                        options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
                                                        value={item.rack_id}
                                                        onChange={(v) => updateItem(item.product_id, 'rack_id', v as string)}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 w-20">
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(item.product_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        min={0}
                                                        max={item.stock}
                                                        disabled={item.stock === 0}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {pageErrors.items && <p className="mt-2 text-sm text-red-500">{pageErrors.items}</p>}
                    </ComponentCard>
                </div>

                <div className="mt-4 flex gap-3">
                    <Button
                        type="submit"
                        disabled={!hasActiveItems || submitting}
                        icon={<CheckIcon className="w-4 h-4" />}
                    >
                        {submitting ? 'Menyimpan...' : 'Update Shipment'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                </div>
            </form>

            <QrScanner
                isOpen={scannerOpen}
                onClose={() => setScannerOpen(false)}
                onScan={handleScan}
            />
        </AppLayout>
    );
}
```

- [ ] **Step 2: Verify TypeScript compiles**

```bash
npx tsc --noEmit 2>&1 | grep -i "Shipments/Edit"
```

Expected: no errors for Edit.tsx.

---

## Task 4: Full Test Run

- [ ] **Step 1: Run the full Shipment test suite**

```bash
php artisan test tests/Feature/ShipmentControllerTest.php
```

Expected: all tests PASS including the 2 new ones. The existing tests (`test_store_creates_shipment`, `test_ship_deducts_stock`, etc.) must remain green — backend `store()` and `update()` are unchanged so they should pass.

- [ ] **Step 2: Run TypeScript check on the whole project**

```bash
npx tsc --noEmit 2>&1 | grep "error TS" | grep -v "baseUrl"
```

Expected: no output (the only warning is the pre-existing `baseUrl` deprecation which is not an error).

- [ ] **Step 3: Manual browser smoke test**

Start dev server and verify:
1. `/shipments/create` — Unit dropdown shows distinct brand+name combinations
2. Select Unit → Suffix dropdown populates
3. Select Suffix → product table appears, stok shown, qty=0
4. Products with stok=0 shown in red, qty input disabled
5. Type qty for one row → Simpan button enables
6. Scan QR (needs HTTPS/localhost) → matching part qty+1, beep
7. Scan unknown part → orange warning
8. Scan part with stok=0 → yellow warning
9. Submit → redirects to Show page, draft created
10. `/shipments/{id}/edit` on a draft → Unit+Suffix pre-selected, qty pre-filled from saved items
11. Change Unit → table resets to qty=0
