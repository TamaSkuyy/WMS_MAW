# QR Quick Receive Design

**Date:** 2026-06-29
**Status:** Approved

---

## Background

Sometimes goods arrive from a supplier without a pre-created cycle (no advance notice or delivery order). The current workflow requires creating a cycle first, then receiving against it — two steps. This is friction when goods are already physically present.

Quick Receive allows the warehouse operator to open a receiving session, scan each item's QR code one by one (each scan = +1 unit), review the accumulated list, and submit — creating the cycle and updating stock in a single transaction.

---

## Goals

1. Add a "Terima Cepat" entry point on the Cycles index page.
2. Operator selects supplier, then enters scan mode using the phone camera.
3. Each QR scan adds the product to the list (or increments qty if already present).
4. Rack auto-fills from `product.default_rack_id` if set.
5. Operator can review and edit qty/rack before submitting.
6. Submit creates the cycle (status = `completed`) + cycle_items + stock updates in one DB transaction.

---

## User Flow

```
Cycles Index
  └─ [Terima Cepat] button
        └─ QuickReceive page
              ├─ Step 1: Pilih Supplier
              └─ Step 2: Scan + Review
                    ├─ [Scan QR] button → opens camera modal
                    │     ├─ Scan → match part_number → row +1 qty + beep
                    │     ├─ Scan unknown part → orange toast warning
                    │     └─ [Tutup Kamera] closes modal
                    ├─ Review table (editable qty + rack)
                    └─ [Selesaikan Penerimaan] → POST → cycle created + stock updated
```

---

## QR Code Format

Based on actual parts in use, the QR code content is the raw part number string (e.g. `P5154-0KA2F-43`). Matching is case-insensitive after stripping leading/trailing whitespace.

---

## Components

### `resources/js/Components/QrScanner.tsx`

Reusable modal component. Uses `html5-qrcode` library.

**Props:**
```ts
interface QrScannerProps {
    isOpen: boolean;
    onClose: () => void;
    onScan: (decoded: string) => void;
}
```

**Behavior:**
- When `isOpen` becomes true: start camera, mount `Html5Qrcode` instance into a div.
- When `isOpen` becomes false: stop camera and release.
- On successful decode: call `onScan(decoded.trim())`, then enforce 800ms debounce before next scan (prevent double-count from single label).
- Show last scanned value in modal footer.
- On camera permission denied: show message "Izinkan akses kamera di browser".

### `resources/js/Pages/Transactions/Cycles/QuickReceive.tsx`

Main page. Two logical sections:

**Section 1 — Supplier selector:**  
SearchableSelect of suppliers. Once selected, moves to scan+review section.

**Section 2 — Scan + Review:**  
- Header: "Total item: N jenis, Total qty: M pcs" counter
- [Scan QR] button — opens QrScanner modal
- Review table columns: Part Number | Nama Produk | Rack | Qty | (hapus)
- Rack cell: SearchableSelect pre-filled from `product.default_rack_id`; editable
- Qty cell: number input, editable
- Row delete button (×)
- [Selesaikan Penerimaan] submit button (disabled if list empty or any rack not selected)

**Scan handler logic:**
```
onScan(code):
  product = products.find(p => p.part_number.toLowerCase() === code.toLowerCase())
  if not found:
    show toast warning "Part {code} tidak dikenal"
    return
  existing = scannedItems.find(i => i.product_id === product.id)
  if existing:
    existing.qty += 1
  else:
    scannedItems.push({ product_id, part_number, name, qty: 1, rack_id: product.default_rack_id || '' })
  play beep
  flash row green
```

**Audio feedback:** Web Audio API oscillator, 880Hz, 60ms — no external file required.

---

## Backend

### New route (in `web.php`)

```php
Route::get('cycles/quick-receive', [CycleController::class, 'quickReceiveForm'])->name('cycles.quick-receive.form');
Route::post('cycles/quick-receive', [CycleController::class, 'quickReceiveStore'])->name('cycles.quick-receive.store');
```

Routes must be registered **before** `Route::resource('cycles', ...)` to avoid conflict with `{cycle}` wildcard.

### `CycleController::quickReceiveForm()`

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

### `CycleController::quickReceiveStore()`

Validates and creates cycle + items + stock in a single DB transaction.

**Validation:**
```php
'supplier_id'          => 'required|exists:suppliers,id'
'items'                => 'required|array|min:1'
'items.*.product_id'   => 'required|exists:products,id'
'items.*.rack_id'      => 'required|exists:racks,id'
'items.*.quantity'     => 'required|integer|min:1'
```

**Logic (inside `DB::transaction`):**
1. Generate `cycle_number`: `Cycle::where('supplier_id', $supplierId)->max('cycle_number') + 1`
2. Create `Cycle` with status = `completed`, `received_at` = now()
3. For each item: create `CycleItem` (quantity = received_quantity = item qty, rack_id set)
4. For each item: `Stock::firstOrNew([product_id, rack_id])`, increment quantity, save

**Returns:** redirect to `cycles.show` with success flash.

---

## Data Constraints

- `cycle_number` auto-generated per supplier (max + 1), not user-entered for Quick Receive.
- `notes` field omitted (not needed for quick scan flow; can be added later).
- Duplicate product in submitted items: blocked by frontend (can only appear once per product_id). Backend validation also rejects duplicate `product_id` in items array.

---

## Navigation

Add "Terima Cepat" button on `Cycles/Index.tsx` next to the existing "Tambah Cycle" button.

---

## Testing

- `test_quick_receive_form_loads` — GET returns 200, passes suppliers + products + racks
- `test_quick_receive_creates_cycle_and_updates_stock` — POST creates cycle (status=completed), cycle_items with rack_id, stock updated
- `test_quick_receive_validates_required_fields` — POST empty body → validation errors
- `test_quick_receive_rejects_unknown_product` — POST with invalid product_id → 422

---

## Out of Scope

- Notes field on quick receive (can be added later)
- Barcode (non-QR) support — only QR for now
- Offline/PWA scanning
- Printing new QR codes from this system
