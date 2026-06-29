# Shopping — Unit + Suffix Trigger Design

**Date:** 2026-06-29
**Status:** Approved

---

## Background

Input item pada halaman Shopping (Shipments) saat ini menggunakan pendekatan satu per satu: operator memilih produk, rack, dan qty secara manual per baris. Pendekatan ini lambat karena pengiriman biasanya mengikuti pola tertentu — semua part untuk satu varian kendaraan dikirim sekaligus.

Redesign ini mengganti mekanisme input dengan trigger **Unit + Suffix**: operator memilih model kendaraan (Unit) dan variannya (Suffix), lalu seluruh part yang terkait muncul otomatis dalam tabel. Operator hanya perlu mengisi qty per baris (atau scan QR).

---

## Goals

1. Ganti item-selection section pada Create dan Edit dengan trigger Unit + Suffix.
2. Part muncul otomatis saat Unit + Suffix dipilih, rack pre-fill dari `product.default_rack_id`.
3. Stok ditampilkan per baris — baris dengan stok 0 di-highlight merah dan qty-nya disabled.
4. QR scan tersedia: tiap scan menambah qty +1 pada part yang cocok di tabel.
5. Submit hanya mengirim baris dengan qty > 0.
6. Edit page: deteksi vehicle_model dari items yang ada, pre-select Unit + Suffix, isi qty dari data tersimpan.

---

## Tidak Ada Perubahan Skema

Semua field yang dibutuhkan sudah tersedia:
- `vehicle_models`: `id`, `brand`, `name`, `suffix`
- `products`: `vehicle_model_id`, `part_number`, `name`, `default_rack_id`
- `stocks`: `product_id`, `rack_id`, `quantity`
- `shipment_items`: `shipment_id`, `product_id`, `rack_id`, `quantity`

---

## User Flow

```
Shipments/Create (atau Edit)
  ├─ Card kiri: Info Shipment
  │    ├─ Nama Mitra *
  │    ├─ Tanggal Kirim *
  │    ├─ Catatan
  │    ├─ ─────────────────
  │    ├─ Unit *   [ Toyota Rush ▼ ]
  │    └─ Suffix * [ TRD          ▼ ]  (disabled sampai Unit dipilih)
  │
  └─ Card kanan: Daftar Part
       ├─ [📷 Scan QR]  (aktif setelah Unit+Suffix dipilih)
       ├─ Placeholder "Pilih Unit & Suffix untuk memuat part" (sebelum dipilih)
       └─ Tabel setelah dipilih:
            Part # | Nama | Stok | Rack     | Qty
            ──────────────────────────────────────
            P001   | ...  |  5   | [A-01 ▼] | [0]
            P002   | ...  |  0   | [B-02 ▼] | [0] ← merah, qty disabled
            P003   | ...  |  2   | [A-01 ▼] | [1]
       └─ [Simpan Shipment] (disabled jika tidak ada qty > 0)
```

---

## Komponen

### `QrScanner.tsx` (sudah ada)

Dipakai ulang dari implementasi Quick Receive. Tidak ada perubahan.

**Perilaku scan pada Shopping:**
- Scan QR (berisi `part_number`) → cari di tabel produk yang sedang aktif
- Jika ditemukan dan stok > 0: qty +1, beep
- Jika ditemukan tapi stok 0: abaikan (qty disabled)
- Jika tidak ditemukan di tabel aktif: tampilkan warning orange "Part tidak termasuk unit ini"

---

### `Shipments/Create.tsx`

**State baru:**
```ts
const [selUnit, setSelUnit] = useState('');       // "Toyota Rush"
const [selSuffix, setSelSuffix] = useState('');   // "TRD"
const [scannerOpen, setScannerOpen] = useState(false);
const [tableItems, setTableItems] = useState<TableItem[]>([]);
// items di useForm hanya diisi saat submit
```

**Derived data (useMemo):**
```ts
// Distinct brand+name pairs
const units = useMemo(() =>
  [...new Map(vehicleModels.map(m => [`${m.brand} ${m.name}`, m])).values()]
    .map(m => ({ value: `${m.brand} ${m.name}`, label: `${m.brand} ${m.name}` })),
  [vehicleModels]
);

// Suffixes for selected unit — model tanpa suffix masuk sebagai "Standar"
const suffixes = useMemo(() =>
  vehicleModels
    .filter(m => `${m.brand} ${m.name}` === selUnit)
    .map(m => ({ value: m.suffix ?? '', label: m.suffix || 'Standar' })),
  [selUnit, vehicleModels]
);

// Vehicle model id for selected unit+suffix
// suffix === '' cocok dengan model yang suffix-nya null atau ''
const activeVehicleModelId = useMemo(() =>
  vehicleModels.find(m =>
    `${m.brand} ${m.name}` === selUnit &&
    (m.suffix ?? '') === selSuffix
  )?.id,
  [selUnit, selSuffix, vehicleModels]
);
```

**Populate tabel** (efek saat activeVehicleModelId berubah):
```ts
useEffect(() => {
  if (!activeVehicleModelId) { setTableItems([]); return; }
  const filtered = products.filter(p => p.vehicle_model_id === activeVehicleModelId);
  setTableItems(filtered.map(p => ({
    product_id: p.id,
    part_number: p.part_number,
    name: p.name,
    stock: p.stocks.find(s => s.rack_id === p.default_rack_id)?.quantity ?? 0,
    rack_id: p.default_rack_id ? String(p.default_rack_id) : '',
    quantity: 0,
  })));
}, [activeVehicleModelId]);
```

**Scan handler:**
```ts
const handleScan = useCallback((code: string) => {
  const item = tableItems.find(i => i.part_number.toLowerCase() === code.toLowerCase());
  if (!item) { /* warning: part tidak termasuk unit ini */ return; }
  if (item.stock <= 0) return;  // stok 0, abaikan
  beep();
  setTableItems(prev => prev.map(i =>
    i.product_id === item.product_id ? { ...i, quantity: i.quantity + 1 } : i
  ));
}, [tableItems]);
```

**Submit:**
```ts
const handleSubmit = (e) => {
  e.preventDefault();
  const activeItems = tableItems.filter(i => i.quantity > 0);
  setData('items', activeItems.map(i => ({
    product_id: String(i.product_id),
    rack_id: i.rack_id,
    quantity: i.quantity,
  })));
  // post() dipanggil di useEffect setelah data.items terupdate
};
```

**Tabel kolom:**
- Part # (font-mono, xs)
- Nama Produk
- Stok (badge hijau jika > 0, merah jika 0)
- Rack (SearchableSelect, pre-fill dari default_rack_id, editable)
- Qty (Input number, disabled jika stok = 0)

**Baris highlight:** `className={item.stock === 0 ? 'bg-red-50 dark:bg-red-900/10 opacity-60' : ''}`

---

### `Shipments/Edit.tsx`

Sama seperti Create dengan tambahan **reconcile on load**:

```ts
// Pre-select Unit + Suffix dari item pertama
useEffect(() => {
  if (!shipment.items?.length) return;
  const firstProduct = products.find(p => p.id === shipment.items[0].product_id);
  if (!firstProduct?.vehicle_model) return;
  const vm = firstProduct.vehicle_model;
  setSelUnit(`${vm.brand} ${vm.name}`);
  setSelSuffix(vm.suffix ?? '');
}, []);

// Setelah tabel populate, isi qty dari items yang tersimpan
useEffect(() => {
  if (!tableItems.length) return;
  const savedQtyMap = Object.fromEntries(
    shipment.items.map(i => [i.product_id, { qty: i.quantity, rack_id: i.rack_id }])
  );
  setTableItems(prev => prev.map(i => ({
    ...i,
    quantity: savedQtyMap[i.product_id]?.qty ?? 0,
    rack_id: savedQtyMap[i.product_id]?.rack_id
      ? String(savedQtyMap[i.product_id].rack_id)
      : i.rack_id,
  })));
}, [activeVehicleModelId]);
```

Jika operator ganti Unit atau Suffix di Edit: qty semua reset ke 0 (konfirmasi tidak diperlukan — sudah jelas dari UI).

---

## Backend

### `ShipmentController::create()`

```php
public function create()
{
    return Inertia::render('Transactions/Shipments/Create', [
        'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
        'products'      => Product::with(['vehicleModel', 'stocks'])
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get(),
        'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
    ]);
}
```

### `ShipmentController::edit()`

```php
public function edit(Shipment $shipment)
{
    if ($shipment->status !== 'draft') {
        return back()->with('error', 'Only draft shipments can be edited.');
    }
    return Inertia::render('Transactions/Shipments/Edit', [
        'shipment'      => $shipment->load('items.product.vehicleModel'),
        'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
        'products'      => Product::with(['vehicleModel', 'stocks'])
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get(),
        'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
    ]);
}
```

`store()` dan `update()` tidak berubah — sudah accept format `items[].{product_id, rack_id, quantity}`.

---

## Submit Handling

Karena items dikelola di state lokal (`tableItems`) bukan di `useForm`, submit langsung pakai `router.post()` / `router.put()` dengan data inline — lebih sederhana daripada sinkronisasi `useForm.setData()`:

```ts
import { router } from '@inertiajs/react';

const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(route('shipments.store'), {
        partner_name:   data.partner_name,
        shipment_date:  data.shipment_date,
        notes:          data.notes,
        items: tableItems
            .filter(i => i.quantity > 0)
            .map(i => ({ product_id: i.product_id, rack_id: i.rack_id, quantity: i.quantity })),
    });
};
// Untuk Edit: router.put(route('shipments.update', shipment.id), { ... })
```

`useForm` tetap dipakai untuk `partner_name`, `shipment_date`, `notes` (validasi error per field). `tableItems` dikelola terpisah sebagai local state.

---

## Testing

**`tests/Feature/ShipmentControllerTest.php`** — 2 test baru:

```php
public function test_create_passes_vehicle_models_with_products_and_stocks(): void
{
    $vm = VehicleModel::factory()->create(['suffix' => 'TRD']);
    $rack = Rack::factory()->create();
    $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
    Stock::factory()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

    $response = $this->actingAs($this->user)->get(route('shipments.create'));

    $response->assertStatus(200);
}

public function test_store_only_saves_items_with_quantity_from_controller(): void
{
    $vm = VehicleModel::factory()->create(['suffix' => 'TRD']);
    $rack = Rack::factory()->create();
    $product = Product::factory()->create(['vehicle_model_id' => $vm->id]);

    $response = $this->actingAs($this->user)->post(route('shipments.store'), [
        'partner_name'   => 'PT Test',
        'shipment_date'  => '2026-06-29',
        'items' => [
            ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
        ],
    ]);

    $this->assertDatabaseHas('shipment_items', [
        'product_id' => $product->id,
        'quantity'   => 3,
    ]);
    $response->assertRedirect();
}
```

---

## Out of Scope

- Shipment tanpa pola Unit+Suffix (custom one-by-one) — dihapus, diganti sepenuhnya
- Warning konfirmasi saat ganti Unit/Suffix di Edit — tidak diperlukan
- Validasi stok saat input di Create/Edit — cukup visual; validasi keras tetap di `ship()`
