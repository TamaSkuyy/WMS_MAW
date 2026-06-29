# QR Quick Receive Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Terima Cepat" flow where the operator scans QR codes from a phone camera, each scan adds the product to a receiving list (+1 per scan), and submitting creates a completed cycle + updates stock in one transaction.

**Architecture:** New `QrScanner.tsx` reusable modal component using `html5-qrcode`. New `QuickReceive.tsx` page holds scan state and review table. Two new controller methods (`quickReceiveForm` / `quickReceiveStore`) added to `CycleController`. Routes registered before `Route::resource('cycles', ...)` to prevent wildcard conflict.

**Tech Stack:** React 18, TypeScript, Inertia.js, `html5-qrcode` npm, Laravel 11, Web Audio API (beep, no file).

---

## File Map

| File | Change |
|------|--------|
| `package.json` | Add `html5-qrcode` dependency |
| `routes/web.php` | Add 2 routes before cycles resource |
| `app/Http/Controllers/CycleController.php` | Add `quickReceiveForm()` + `quickReceiveStore()` |
| `resources/js/Components/QrScanner.tsx` | New — reusable camera modal |
| `resources/js/Pages/Transactions/Cycles/QuickReceive.tsx` | New — scan + review page |
| `resources/js/Pages/Transactions/Cycles/Index.tsx` | Add "Terima Cepat" button |
| `tests/Feature/CycleControllerTest.php` | 3 new tests |

---

## Task 1: Install html5-qrcode

**Files:**
- Modify: `package.json` (via npm)

- [ ] **Step 1: Install the package**

```bash
npm install html5-qrcode --legacy-peer-deps
```

Expected: `html5-qrcode` appears in `package.json` dependencies, no errors.

- [ ] **Step 2: Verify TypeScript types are bundled**

```bash
ls node_modules/html5-qrcode/esm/
```

Expected: see `html5-qrcode.d.ts` or similar — types are included in the package, no `@types/` install needed.

---

## Task 2: Backend — Routes + Controller + Tests

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/CycleController.php`
- Modify: `tests/Feature/CycleControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Add these 3 tests to `tests/Feature/CycleControllerTest.php` (imports already present: `Cycle`, `CycleItem`, `Product`, `Rack`, `Supplier`, `User`):

```php
public function test_quick_receive_form_returns_200(): void
{
    $response = $this->actingAs($this->user)->get(route('cycles.quick-receive.form'));
    $response->assertStatus(200);
}

public function test_quick_receive_creates_cycle_and_updates_stock(): void
{
    $supplier = Supplier::factory()->create();
    $rack = Rack::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), [
        'supplier_id' => $supplier->id,
        'items' => [
            ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
        ],
    ]);

    $this->assertDatabaseHas('cycles', [
        'supplier_id' => $supplier->id,
        'status' => 'completed',
    ]);
    $this->assertDatabaseHas('cycle_items', [
        'product_id' => $product->id,
        'quantity' => 3,
        'received_quantity' => 3,
        'rack_id' => $rack->id,
    ]);
    $this->assertDatabaseHas('stocks', [
        'product_id' => $product->id,
        'rack_id' => $rack->id,
        'quantity' => 3,
    ]);
    $response->assertRedirect();
}

public function test_quick_receive_validates_required(): void
{
    $response = $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), []);
    $response->assertSessionHasErrors(['supplier_id', 'items']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter="test_quick_receive"
```

Expected: FAIL — routes don't exist yet (404 or route not found).

- [ ] **Step 3: Add routes to web.php**

Open `routes/web.php`. Add these two lines **before** `Route::resource('cycles', CycleController::class)`:

```php
Route::get('cycles/quick-receive', [CycleController::class, 'quickReceiveForm'])->name('cycles.quick-receive.form');
Route::post('cycles/quick-receive', [CycleController::class, 'quickReceiveStore'])->name('cycles.quick-receive.store');
```

The file section should now read:

```php
// Transaction routes
Route::resource('racks', RackController::class);
Route::get('cycles/quick-receive', [CycleController::class, 'quickReceiveForm'])->name('cycles.quick-receive.form');
Route::post('cycles/quick-receive', [CycleController::class, 'quickReceiveStore'])->name('cycles.quick-receive.store');
Route::resource('cycles', CycleController::class);
Route::post('cycles/{cycle}/receive', [CycleController::class, 'receive'])->name('cycles.receive');
```

- [ ] **Step 4: Add DB import to CycleController**

At the top of `app/Http/Controllers/CycleController.php`, add:

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 5: Add quickReceiveForm() to CycleController**

Add this method after the existing `destroy()` method in `app/Http/Controllers/CycleController.php`:

```php
public function quickReceiveForm()
{
    return Inertia::render('Transactions/Cycles/QuickReceive', [
        'suppliers' => Supplier::orderBy('name')->get(),
        'products'  => Product::with('defaultRack')->where('is_active', true)->orderBy('name')->get(),
        'racks'     => Rack::orderBy('zone')->orderBy('code')->get(),
    ]);
}
```

- [ ] **Step 6: Add quickReceiveStore() to CycleController**

Add this method immediately after `quickReceiveForm()`:

```php
public function quickReceiveStore(Request $request)
{
    $validated = $request->validate([
        'supplier_id'        => 'required|exists:suppliers,id',
        'items'              => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.rack_id'    => 'required|exists:racks,id',
        'items.*.quantity'   => 'required|integer|min:1',
    ]);

    $cycle = DB::transaction(function () use ($validated) {
        $supplierId  = $validated['supplier_id'];
        $cycleNumber = (Cycle::where('supplier_id', $supplierId)->max('cycle_number') ?? 0) + 1;

        $cycle = Cycle::create([
            'supplier_id'  => $supplierId,
            'cycle_number' => $cycleNumber,
            'status'       => 'completed',
            'received_at'  => now(),
        ]);

        foreach ($validated['items'] as $item) {
            $cycle->items()->create([
                'product_id'        => $item['product_id'],
                'quantity'          => $item['quantity'],
                'received_quantity' => $item['quantity'],
                'rack_id'           => $item['rack_id'],
            ]);

            $stock = Stock::firstOrNew([
                'product_id' => $item['product_id'],
                'rack_id'    => $item['rack_id'],
            ]);
            $stock->quantity += $item['quantity'];
            $stock->save();
        }

        return $cycle;
    });

    return redirect()->route('cycles.show', $cycle)->with('success', 'Barang diterima. Stock diperbarui.');
}
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
php artisan test --filter="test_quick_receive"
```

Expected: all 3 tests PASS.

---

## Task 3: QrScanner Component

**Files:**
- Create: `resources/js/Components/QrScanner.tsx`

- [ ] **Step 1: Create the component**

```tsx
import React, { useEffect, useRef } from 'react';
import { Html5Qrcode } from 'html5-qrcode';

interface QrScannerProps {
    isOpen: boolean;
    onClose: () => void;
    onScan: (decoded: string) => void;
}

export default function QrScanner({ isOpen, onClose, onScan }: QrScannerProps) {
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const lastScanRef = useRef<{ code: string; time: number }>({ code: '', time: 0 });
    const DEBOUNCE_MS = 800;

    useEffect(() => {
        if (!isOpen) return;

        const scanner = new Html5Qrcode('qr-reader');
        scannerRef.current = scanner;

        scanner
            .start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    const code = decodedText.trim();
                    const now = Date.now();
                    if (
                        code === lastScanRef.current.code &&
                        now - lastScanRef.current.time < DEBOUNCE_MS
                    ) {
                        return;
                    }
                    lastScanRef.current = { code, time: now };
                    onScan(code);
                },
                undefined
            )
            .catch(() => {});

        return () => {
            scanner.stop().catch(() => {});
        };
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-sm mx-4 p-4">
                <div className="flex justify-between items-center mb-3">
                    <h3 className="font-semibold text-gray-800 dark:text-white">Scan QR Code</h3>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 text-2xl leading-none"
                    >
                        &times;
                    </button>
                </div>
                <div id="qr-reader" className="w-full rounded-lg overflow-hidden" />
                <p className="mt-3 text-xs text-center text-gray-500">
                    Arahkan kamera ke QR Code pada barang
                </p>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verify TypeScript compiles**

```bash
npx tsc --noEmit 2>&1 | grep QrScanner
```

Expected: no errors for QrScanner.tsx.

---

## Task 4: QuickReceive Page

**Files:**
- Create: `resources/js/Pages/Transactions/Cycles/QuickReceive.tsx`

- [ ] **Step 1: Create the page**

```tsx
import React, { useState, useCallback } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Label from '../../../Tailadmin/components/form/Label';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';
import { Link } from '@inertiajs/react';
import QrScanner from '../../../Components/QrScanner';

interface ScannedItem {
    product_id: number;
    part_number: string;
    name: string;
    quantity: number;
    rack_id: string;
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

export default function QuickReceive({ suppliers, products, racks }: any) {
    const [supplierId, setSupplierId] = useState('');
    const [scannerOpen, setScannerOpen] = useState(false);
    const [items, setItems] = useState<ScannedItem[]>([]);
    const [lastScan, setLastScan] = useState('');
    const [lastScanStatus, setLastScanStatus] = useState<'ok' | 'unknown' | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const handleScan = useCallback(
        (code: string) => {
            const product = products.find(
                (p: any) => p.part_number.toLowerCase() === code.toLowerCase()
            );

            if (!product) {
                setLastScan(code);
                setLastScanStatus('unknown');
                return;
            }

            beep();
            setLastScan(code);
            setLastScanStatus('ok');

            setItems((prev) => {
                const existing = prev.find((i) => i.product_id === product.id);
                if (existing) {
                    return prev.map((i) =>
                        i.product_id === product.id
                            ? { ...i, quantity: i.quantity + 1 }
                            : i
                    );
                }
                return [
                    ...prev,
                    {
                        product_id: product.id,
                        part_number: product.part_number,
                        name: product.name,
                        quantity: 1,
                        rack_id: product.default_rack_id
                            ? String(product.default_rack_id)
                            : '',
                    },
                ];
            });
        },
        [products]
    );

    const updateItem = (productId: number, field: string, value: any) => {
        setItems((prev) =>
            prev.map((i) =>
                i.product_id === productId ? { ...i, [field]: value } : i
            )
        );
    };

    const removeItem = (productId: number) => {
        setItems((prev) => prev.filter((i) => i.product_id !== productId));
    };

    const totalQty = items.reduce((sum, i) => sum + i.quantity, 0);
    const canSubmit =
        !!supplierId && items.length > 0 && items.every((i) => !!i.rack_id);

    const handleSubmit = () => {
        if (!canSubmit || submitting) return;
        setSubmitting(true);
        router.post(
            route('cycles.quick-receive.store'),
            {
                supplier_id: supplierId,
                items: items.map((i) => ({
                    product_id: i.product_id,
                    rack_id: i.rack_id,
                    quantity: i.quantity,
                })),
            },
            { onFinish: () => setSubmitting(false) }
        );
    };

    return (
        <AppLayout>
            <Head title="Terima Cepat" />
            <PageBreadcrumb pageTitle="Terima Cepat (Scan QR)" />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {/* Left panel */}
                <div className="xl:col-span-1">
                    <ComponentCard
                        title="Setup Penerimaan"
                        action={
                            <Link href={route('cycles.index')}>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    icon={<ArrowLeftIcon className="w-4 h-4" />}
                                >
                                    Kembali
                                </Button>
                            </Link>
                        }
                    >
                        <div className="space-y-4">
                            <div>
                                <Label>Supplier *</Label>
                                <SearchableSelect
                                    options={suppliers.map((s: any) => ({
                                        value: s.id,
                                        label: s.name,
                                    }))}
                                    value={supplierId}
                                    onChange={(v) => setSupplierId(v as string)}
                                />
                            </div>

                            <Button
                                onClick={() => setScannerOpen(true)}
                                disabled={!supplierId}
                                className="w-full"
                            >
                                📷 Scan QR Code
                            </Button>

                            {lastScan && (
                                <div
                                    className={`text-xs px-3 py-2 rounded ${
                                        lastScanStatus === 'ok'
                                            ? 'bg-green-50 text-green-700'
                                            : 'bg-orange-50 text-orange-700'
                                    }`}
                                >
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}
                                    {lastScan}
                                    {lastScanStatus === 'unknown' &&
                                        ' — Part tidak dikenal'}
                                </div>
                            )}

                            <div className="pt-2 border-t border-gray-100 text-sm text-gray-500 space-y-1">
                                <div>
                                    Jenis produk:{' '}
                                    <span className="font-medium text-gray-800">
                                        {items.length}
                                    </span>
                                </div>
                                <div>
                                    Total qty:{' '}
                                    <span className="font-medium text-gray-800">
                                        {totalQty}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </ComponentCard>
                </div>

                {/* Right panel */}
                <div className="xl:col-span-2">
                    <ComponentCard
                        title="Daftar Barang Diterima"
                        desc="Hasil scan — review sebelum submit"
                    >
                        {items.length === 0 ? (
                            <p className="text-sm text-gray-400 py-8 text-center">
                                Belum ada barang. Pilih supplier lalu scan QR.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Part #
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Produk
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Rack
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Qty
                                            </th>
                                            <th className="px-3 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {items.map((item) => (
                                            <tr
                                                key={item.product_id}
                                                className={
                                                    item.rack_id
                                                        ? ''
                                                        : 'bg-red-50 dark:bg-red-900/10'
                                                }
                                            >
                                                <td className="px-3 py-2 text-xs font-mono whitespace-nowrap">
                                                    {item.part_number}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    {item.name}
                                                </td>
                                                <td className="px-3 py-2 w-36">
                                                    <SearchableSelect
                                                        options={racks.map((r: any) => ({
                                                            value: r.id,
                                                            label: r.code,
                                                        }))}
                                                        value={item.rack_id}
                                                        onChange={(v) =>
                                                            updateItem(
                                                                item.product_id,
                                                                'rack_id',
                                                                v as string
                                                            )
                                                        }
                                                    />
                                                    {!item.rack_id && (
                                                        <p className="text-xs text-red-500 mt-0.5">
                                                            Pilih rack
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 w-20">
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                item.product_id,
                                                                'quantity',
                                                                parseInt(e.target.value) || 1
                                                            )
                                                        }
                                                        min={1}
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <button
                                                        onClick={() =>
                                                            removeItem(item.product_id)
                                                        }
                                                        className="text-red-500 text-sm hover:text-red-700"
                                                    >
                                                        &#x2715;
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {items.length > 0 && (
                            <div className="mt-4 pt-4 border-t border-gray-100 flex gap-3">
                                <Button
                                    onClick={handleSubmit}
                                    disabled={!canSubmit || submitting}
                                >
                                    {submitting
                                        ? 'Menyimpan...'
                                        : 'Selesaikan Penerimaan'}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setItems([]);
                                        setLastScan('');
                                        setLastScanStatus(null);
                                    }}
                                >
                                    Reset
                                </Button>
                            </div>
                        )}
                    </ComponentCard>
                </div>
            </div>

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
npx tsc --noEmit 2>&1 | grep -E "QuickReceive|QrScanner|error"
```

Expected: no errors.

---

## Task 5: Add "Terima Cepat" Button to Index

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/Index.tsx`

- [ ] **Step 1: Update the button section**

Find the existing button block in `Cycles/Index.tsx`:

```tsx
<div className="mb-3">
    <Link href={route('cycles.create')}><Button>Cycle Baru</Button></Link>
</div>
```

Replace with:

```tsx
<div className="mb-3 flex gap-2">
    <Link href={route('cycles.create')}><Button>Cycle Baru</Button></Link>
    <Link href={route('cycles.quick-receive.form')}>
        <Button variant="outline">📷 Terima Cepat</Button>
    </Link>
</div>
```

---

## Task 6: Full Tests

- [ ] **Step 1: Run all cycle and product tests**

```bash
php artisan test tests/Feature/CycleControllerTest.php tests/Feature/ProductControllerTest.php
```

Expected: `tests/Feature/CycleControllerTest.php` — all new tests pass. Only the 2 pre-existing `ProductControllerTest` filter tests fail (known pre-existing bug unrelated to this feature).

- [ ] **Step 2: Manual browser test on mobile**

Open the app on a phone browser (must be `https://` or `localhost`). Navigate to Receiving → Terima Cepat. Confirm:
- Supplier dropdown works
- "Scan QR Code" button opens camera modal
- Scanning a known part number adds it to the table
- Scanning the same QR again increments qty
- Scanning an unknown code shows orange warning
- Rack auto-fills for products with `default_rack_id`
- "Selesaikan Penerimaan" is disabled until all racks are selected
- Submit redirects to the cycle show page with stock updated
