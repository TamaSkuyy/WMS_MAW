# Receiving Mobile-Friendly UI/UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat form Terima Barang (Cycles/Show) dan Terima Cepat (QuickReceive) mobile-friendly: kartu item di HP, tabel di desktop, QtyStepper besar, sticky action bar — plus sentuhan ringan di Cycles/Index.

**Architecture:** Komponen baru `QtyStepper` (reusable) + dual rendering (`hidden md:block` tabel / `md:hidden` kartu) di Show & QuickReceive. Murni presentasi — logika receive/scan/submit tidak diubah.

**Tech Stack:** Laravel + Inertia + React 18 + TypeScript + Tailwind CSS v4.

**Spec acuan:** `docs/superpowers/specs/2026-08-14-receiving-mobile-uiux-design.md`

## Global Constraints

- **Logika tidak diubah**: handleScan (+1), handleReceive/submit payload, validasi rack, missingRack, flash/error Alert — semua tetap.
- **Dark mode wajib**: semua elemen baru menyertakan `dark:` kelas.
- **Target sentuh min 44px** di elemen interaktif baru.
- **Tidak ada commit**: user yang commit sendiri. JANGAN jalankan `git commit`/`git add`.
- **Tidak ada test framework frontend** (keputusan project): verifikasi = `npm run build` + checklist visual.
- Breakpoint dual rendering: `md` (768px) — `< md` = kartu, `≥ md` = tabel.
- File `Show.tsx` & `QuickReceive.tsx` saat ini sudah berisi perbaikan sebelumnya (Alert flash/error, `itemsRef`, `updateItem` immutable, `selectOnFocus` di Input) — JANGAN dihapus.

---

### Task 1: Komponen QtyStepper

**Files:**
- Create: `resources/js/Components/QtyStepper.tsx`

**Interfaces:**
- Consumes: tidak ada.
- Produces: `QtyStepper` — props: `value: number`; `onChange: (n: number) => void`; `min?: number` (default 0); `max?: number`. Dipakai Task 2 & 3.

- [ ] **Step 1: Tulis komponen**

```tsx
import React from 'react';

interface QtyStepperProps {
    value: number;
    onChange: (n: number) => void;
    min?: number;
    max?: number;
}

export default function QtyStepper({ value, onChange, min = 0, max }: QtyStepperProps) {
    const clamp = (n: number) => {
        if (n < min) n = min;
        if (max !== undefined && n > max) n = max;
        return n;
    };

    return (
        <div className="flex items-center gap-1">
            <button
                type="button"
                onClick={() => onChange(clamp(value - 1))}
                disabled={value <= min}
                className="h-11 w-11 inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 text-xl font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                −
            </button>
            <input
                type="text"
                inputMode="numeric"
                value={value}
                onFocus={(e) => e.target.select()}
                onChange={(e) => {
                    const parsed = parseInt(e.target.value);
                    onChange(clamp(isNaN(parsed) ? min : parsed));
                }}
                className="h-11 w-16 text-center text-base font-semibold tabular-nums border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg"
            />
            <button
                type="button"
                onClick={() => onChange(clamp(value + 1))}
                disabled={max !== undefined && value >= max}
                className="h-11 w-11 inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 text-xl font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed"
            >
                +
            </button>
        </div>
    );
}
```

- [ ] **Step 2: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 2: Show.tsx — kartu mobile + tabel desktop + sticky bar

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/Show.tsx`

**Interfaces:**
- Consumes: `QtyStepper` (Task 1); state `items`, `updateItem`, `missingRack`, `canReceive`, `submitting`, `setScannerOpen`, `setIsReceiving` (sudah ada).

- [ ] **Step 1: Import QtyStepper**

Setelah baris `import Alert from '../../../Tailadmin/components/ui/alert/Alert';` tambahkan:

```tsx
import QtyStepper from '../../../Components/QtyStepper';
```

- [ ] **Step 2: Tambah perhitungan progres & total**

Cari:

```tsx
    const missingRack = items.some((it: any) => !it.rack_id);
    const canReceive = !submitting;
```

Ganti dengan:

```tsx
    const missingRack = items.some((it: any) => !it.rack_id);
    const canReceive = !submitting;
    const itemsComplete = items.filter((it: any, i: number) => (it.received_quantity || 0) >= (cycle.items[i]?.quantity || 0)).length;
    const totalQty = items.reduce((s: number, it: any) => s + (it.received_quantity || 0), 0);
```

- [ ] **Step 3: Ganti isi kartu Terima Barang (dari tombol Scan QR sampai penutup `</form>`)**

Cari blok ini (baris 176–249 file saat ini):

```tsx
                            <div className="mb-4">
                                <Button type="button" variant="outline" size="sm" onClick={() => setScannerOpen(true)}>
                                    📷 Scan QR
                                </Button>
                            </div>
                            <form onSubmit={handleReceive}>
                                <div className="overflow-x-auto">
                                <table className="min-w-[850px] sm:min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">Part Number / Nama Produk</th>
                                            <th className="px-2 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase w-14">Qty Doc</th>
                                            <th className="px-2 py-2 text-center text-[10px] font-semibold text-gray-500 uppercase w-16">Diterima</th>
                                            <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase w-32">Rak</th>
                                            <th className="px-2 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase hidden sm:table-cell">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {cycle.items.map((item: any, i: number) => (
                                            <tr key={item.id}>
                                                <td className="px-2 py-2 min-w-[180px]">
                                                    <span className="text-xs font-mono text-gray-700 dark:text-gray-300">{item.product?.part_number}</span>
                                                    <span className="text-xs sm:text-sm text-gray-800 dark:text-white/90 block mt-0.5">{item.product?.name}</span>
                                                </td>
                                                <td className="px-2 py-2 text-xs text-center tabular-nums">{item.quantity}</td>
                                                <td className="px-2 py-2">
                                                    <input
                                                        type="number"
                                                        value={items[i].received_quantity}
                                                        onChange={(e) => updateItem(i, 'received_quantity', parseInt(e.target.value) || 0)}
                                                        onFocus={(e) => e.target.select()}
                                                        min={0}
                                                        className="w-14 sm:w-20 text-center text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded px-1 py-1.5"
                                                    />
                                                </td>
                                                <td className="px-2 py-2">
                                                    <div className="flex flex-col gap-0.5">
                                                        <div className="w-28 sm:w-36">
                                                            <SearchableSelect options={racks.map((r: any) => ({ value: r.id, label: r.code }))} value={items[i].rack_id} onChange={(v) => updateItem(i, 'rack_id', v as string)} />
                                                        </div>
                                                        {!items[i].rack_id ? (
                                                            <span className="text-[10px] font-medium text-amber-600">⚠ Relay</span>
                                                        ) : (
                                                            <>
                                                                {items[i].rack_source === 'default' && (
                                                                    <span className="text-[10px] font-medium text-blue-600">Default</span>
                                                                )}
                                                                {items[i].rack_source === 'history' && (
                                                                    <span className="text-[10px] font-medium text-gray-500">Terakhir</span>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-2 py-2 hidden sm:table-cell">
                                                    <input type="text" value={items[i].notes} onChange={(e) => updateItem(i, 'notes', e.target.value)} className="w-full text-xs border rounded px-2 py-1 dark:bg-gray-800 dark:border-gray-700" placeholder="cth: rusak" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                </div>
                                {missingRack && (
                                    <div className="mt-3 flex items-center gap-2 px-3 py-2 text-sm text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                        <span>⚠️</span> <span>Beberapa item belum memilih rak — stok akan dicatat tanpa lokasi rak (Overflow).</span>
                                    </div>
                                )}
                                <div className="mt-4 flex gap-3">
                                    <Button type="submit" disabled={!canReceive}>
                                        {submitting ? 'Menyimpan...' : 'Selesaikan Penerimaan'}
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setIsReceiving(false)} disabled={submitting}>Batal</Button>
                                </div>
                            </form>
```

Ganti dengan:

```tsx
                            <form onSubmit={handleReceive}>
                                {/* Desktop (≥ md): tabel */}
                                <div className="hidden md:block overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part Number / Nama Produk</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-16">Qty Doc</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Diterima</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Rak</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                        {cycle.items.map((item: any, i: number) => (
                                            <tr key={item.id} className={!items[i].rack_id ? 'bg-red-50 dark:bg-red-900/10' : ''}>
                                                <td className="px-4 py-2.5 min-w-[180px]">
                                                    <span className="text-xs font-mono text-gray-700 dark:text-gray-300">{item.product?.part_number}</span>
                                                    <span className="text-sm text-gray-800 dark:text-white/90 block mt-0.5">{item.product?.name}</span>
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-center tabular-nums">{item.quantity}</td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={items[i].received_quantity}
                                                        onChange={(n) => updateItem(i, 'received_quantity', n)}
                                                        max={item.quantity}
                                                    />
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="flex flex-col gap-0.5">
                                                        <div className="w-40 sm:w-48">
                                                            <SearchableSelect options={racks.map((r: any) => ({ value: r.id, label: r.code }))} value={items[i].rack_id} onChange={(v) => updateItem(i, 'rack_id', v as string)} />
                                                        </div>
                                                        {!items[i].rack_id ? (
                                                            <span className="text-[10px] font-medium text-amber-600">⚠ Relay</span>
                                                        ) : (
                                                            <>
                                                                {items[i].rack_source === 'default' && (
                                                                    <span className="text-[10px] font-medium text-blue-600">Default</span>
                                                                )}
                                                                {items[i].rack_source === 'history' && (
                                                                    <span className="text-[10px] font-medium text-gray-500">Terakhir</span>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <input type="text" value={items[i].notes} onChange={(e) => updateItem(i, 'notes', e.target.value)} className="w-full text-sm border rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700" placeholder="cth: rusak" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                </div>

                                {/* Mobile (< md): kartu per item */}
                                <div className="md:hidden space-y-3">
                                    {cycle.items.map((item: any, i: number) => (
                                        <div key={item.id} className={`rounded-xl border p-4 ${!items[i].rack_id ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-white dark:bg-gray-900'}`}>
                                            <div className="flex justify-between gap-2 mb-3">
                                                <div>
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.product?.part_number}</div>
                                                    <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.product?.name}</div>
                                                </div>
                                                <span className="h-fit whitespace-nowrap px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                    Doc: {item.quantity}
                                                </span>
                                            </div>
                                            <div className="mb-3">
                                                <QtyStepper
                                                    value={items[i].received_quantity}
                                                    onChange={(n) => updateItem(i, 'received_quantity', n)}
                                                    max={item.quantity}
                                                />
                                            </div>
                                            <div className="mb-2">
                                                <SearchableSelect options={racks.map((r: any) => ({ value: r.id, label: r.code }))} value={items[i].rack_id} onChange={(v) => updateItem(i, 'rack_id', v as string)} />
                                                {!items[i].rack_id ? (
                                                    <span className="text-[11px] font-medium text-amber-600">⚠ Relay / tanpa rak</span>
                                                ) : (
                                                    <>
                                                        {items[i].rack_source === 'default' && <span className="text-[11px] font-medium text-blue-600">Default</span>}
                                                        {items[i].rack_source === 'history' && <span className="text-[11px] font-medium text-gray-500">Terakhir</span>}
                                                    </>
                                                )}
                                            </div>
                                            <input type="text" value={items[i].notes} onChange={(e) => updateItem(i, 'notes', e.target.value)} className="w-full h-11 text-sm border rounded-lg px-3 dark:bg-gray-800 dark:border-gray-700" placeholder="Catatan (cth: rusak)" />
                                        </div>
                                    ))}
                                </div>

                                {missingRack && (
                                    <div className="mt-3 flex items-center gap-2 px-3 py-2 text-sm text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                        <span>⚠️</span> <span>Beberapa item belum memilih rak — stok akan dicatat tanpa lokasi rak (Overflow).</span>
                                    </div>
                                )}

                                {/* Sticky action bar */}
                                <div className="sticky bottom-0 z-10 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#E9ECEF] bg-white px-4 py-3 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                    <div className="text-sm font-medium text-[#1A1D23] dark:text-white">
                                        {itemsComplete}/{cycle.items.length} selesai
                                        <span className="text-gray-500 dark:text-gray-400 font-normal"> · {totalQty} qty</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => setScannerOpen(true)} title="Scan QR">
                                            📷
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" onClick={() => setIsReceiving(false)} disabled={submitting}>Batal</Button>
                                        <Button type="submit" disabled={!canReceive}>
                                            {submitting ? 'Menyimpan...' : 'Selesaikan Penerimaan'}
                                        </Button>
                                    </div>
                                </div>
                            </form>
```

Catatan: tombol "Selesaikan Penerimaan" kini `type="submit"` tanpa `onClick` (satu jalur submit via `onSubmit={handleReceive}`). Tombol Scan QR pindah ke sticky bar.

- [ ] **Step 4: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 3: QuickReceive.tsx — kartu mobile + tabel desktop + sticky bar

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/QuickReceive.tsx`

**Interfaces:**
- Consumes: `QtyStepper` (Task 1); state `items`, `updateItem`, `removeItem`, `missingRack`, `totalQty`, `canSubmit`, `submitting`, `handleSubmit`, `setItems`, `setLastScan`, `setLastScanStatus` (sudah ada).

- [ ] **Step 1: Import QtyStepper & hapus import Input bila tidak dipakai lagi**

Setelah baris `import QrScanner from '../../../Components/QrScanner';` tambahkan:

```tsx
import QtyStepper from '../../../Components/QtyStepper';
```

Hapus baris `import Input from '../../../Tailadmin/components/form/input/InputField';` — setelah Step 3 input qty `Input` tidak dipakai lagi di file ini.

- [ ] **Step 2: Ganti tabel item dengan dual rendering**

Cari blok berikut (bagian dalam kartu "Daftar Barang Diterima", kondisi `items.length === 0 ? (...) : (...)`):

```tsx
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
                                                        <span className="text-xs text-amber-600 font-medium">⚠ Relay / Tanpa Rak</span>
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
                                                        selectOnFocus
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
```

Ganti dengan:

```tsx
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Rack</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                            <th className="px-4 py-2.5"></th>
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
                                                <td className="px-4 py-2.5 text-xs font-mono whitespace-nowrap">
                                                    {item.part_number}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.name}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="w-40 sm:w-48">
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
                                                    </div>
                                                    {!item.rack_id && (
                                                        <span className="text-xs text-amber-600 font-medium">⚠ Relay / Tanpa Rak</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, 'quantity', n)}
                                                        min={1}
                                                    />
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <button
                                                        onClick={() => removeItem(item.product_id)}
                                                        className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-500 text-lg hover:bg-red-50 dark:hover:bg-red-500/20"
                                                        title="Hapus"
                                                    >
                                                        ✕
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu per item */}
                            <div className="md:hidden space-y-3">
                                {items.map((item) => (
                                    <div
                                        key={item.product_id}
                                        className={`rounded-xl border p-4 ${
                                            item.rack_id
                                                ? 'border-[#E9ECEF] dark:border-gray-700 bg-white dark:bg-gray-900'
                                                : 'border-red-300 bg-red-50 dark:bg-red-900/10'
                                        }`}
                                    >
                                        <div className="flex justify-between items-start gap-2 mb-3">
                                            <div>
                                                <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                            </div>
                                            <button
                                                onClick={() => removeItem(item.product_id)}
                                                className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-500 text-lg hover:bg-red-50 dark:hover:bg-red-500/20"
                                                title="Hapus"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                        <div className="mb-2">
                                            <QtyStepper
                                                value={item.quantity}
                                                onChange={(n) => updateItem(item.product_id, 'quantity', n)}
                                                min={1}
                                            />
                                        </div>
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
                                            <span className="text-xs text-amber-600 font-medium">⚠ Relay / Tanpa Rak</span>
                                        )}
                                    </div>
                                ))}
                            </div>
```

- [ ] **Step 3: Ganti area tombol bawah dengan sticky bar**

Cari:

```tsx
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
```

Ganti dengan:

```tsx
                        {items.length > 0 && (
                            <div className="sticky bottom-0 z-10 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#E9ECEF] bg-white px-4 py-3 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                <div className="text-sm font-medium text-[#1A1D23] dark:text-white">
                                    {items.length} jenis
                                    <span className="text-gray-500 dark:text-gray-400 font-normal"> · {totalQty} qty</span>
                                </div>
                                <div className="flex gap-2">
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
                                    <Button
                                        onClick={handleSubmit}
                                        disabled={!canSubmit || submitting}
                                    >
                                        {submitting
                                            ? 'Menyimpan...'
                                            : 'Selesaikan Penerimaan'}
                                    </Button>
                                </div>
                            </div>
                        )}
```

Catatan: `handleSubmit` QuickReceive adalah handler tombol (bukan form submit) — perilaku `onClick` dipertahankan.

- [ ] **Step 4: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 4: Index.tsx — sentuhan ringan mobile

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/Index.tsx`

**Interfaces:**
- Consumes: tidak ada komponen baru.

- [ ] **Step 1: Filter full-width di HP**

Cari:

```tsx
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
```

Ganti dengan:

```tsx
                    <div className="w-full sm:min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
```

Cari:

```tsx
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
```

Ganti dengan:

```tsx
                    <div className="w-full sm:min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
```

- [ ] **Step 2: Ganti tombol lihat (TableActions) dengan tombol custom 44px**

Cari:

```tsx
                                            <TableActions
                                                viewRoute={route('cycles.show', cycle.id)}
                                            />
```

Ganti dengan:

```tsx
                                            <Link
                                                href={route('cycles.show', cycle.id)}
                                                className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-[#3B5BDB] bg-[#EEF2FF] hover:bg-[#DBE4FF] transition-all duration-150"
                                                title="Lihat"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>
```

Hapus baris `import TableActions from '../../../Tailadmin/components/common/TableActions';` (tidak dipakai lagi).

- [ ] **Step 3: Perbesar tombol edit & hapus custom ke 44px**

Cari (tombol edit, dua tempat string sama persis — edit di Index ini hanya satu):

```tsx
                                                        className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#F59F00] bg-[#FFF9DB] hover:bg-[#FFF3BF] transition-all duration-150"
```

Ganti dengan:

```tsx
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-[#F59F00] bg-[#FFF9DB] hover:bg-[#FFF3BF] transition-all duration-150"
```

Cari (tombol hapus):

```tsx
                                                        className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg p-2 text-[#FA5252] bg-[#FFF5F5] hover:bg-[#FFE3E3] transition-all duration-150"
```

Ganti dengan:

```tsx
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-[#FA5252] bg-[#FFF5F5] hover:bg-[#FFE3E3] transition-all duration-150"
```

Kedua SVG di dalam tombol edit/hapus: ubah `className="w-4 h-4"` menjadi `className="w-5 h-5"` (masing-masing satu, masih dalam `<Link ... title="Edit">` dan `<button ... title="Hapus">`).

- [ ] **Step 4: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 5: Verifikasi akhir keseluruhan

**Files:** (tidak ada perubahan kode)

- [ ] **Step 1: Build bersih**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error/warning baru.

- [ ] **Step 2: Cek visual HP (390px) — DevTools responsive**

- `cycles/{id}` → klik Terima Barang: item tampil sebagai **kartu** (part#, nama, badge Doc, stepper −/+, rak full-width, catatan); sticky bar bawah tampil `X/Y selesai · N qty` + 📷 + Batal + Selesaikan; scroll tidak menggeser bar.
- Stepper: − disabled di 0; + disabled di qty doc; ketik manual mengganti angka (select-on-focus tetap jalan); scan QR dari tombol 📷 menambah +1.
- `cycles/quick-receive`: item kartu, stepper min 1, rak full-width, tombol hapus 44px; sticky bar: `X jenis · Y qty` + Reset + Selesaikan.

- [ ] **Step 3: Cek tablet (768px) & desktop (1280px)**

- ≥768px: kembali ke tabel (tabel desktop dengan QtyStepper + rak lebih lebar w-40/48); sticky bar tetap tampil.
- `cycles` (index): filter full-width di layar sempit; tombol aksi 44px.

- [ ] **Step 4: Cek dark mode**

- Kartu item, stepper, sticky bar, tabel terbaca dengan kelas `dark:`.

- [ ] **Step 5: Cek perilaku yang tidak berubah**

- Scan QR +1, feedback Alert (✓/error) tampil.
- Warning missingRack & row merah untuk item tanpa rak.
- Submit receive/quick-receive bekerja (backend tidak diubah).
- Flash success/error Alert tetap muncul (dari perbaikan sebelumnya).
