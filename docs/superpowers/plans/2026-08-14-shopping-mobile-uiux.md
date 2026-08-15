# Shopping Mobile-Friendly UI/UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat form Shopping Create/Edit mobile-friendly: kartu item di HP untuk tabel Barang Dipilih & Cari Produk, tabel di desktop, QtyStepper max=stok — plus sentuhan ringan di Shopping/Index.

**Architecture:** Pakai ulang komponen `QtyStepper` (sudah ada) + dual rendering (`hidden md:block` tabel / `md:hidden` kartu) di Create & Edit. Murni presentasi — logika scan/filter/submit tidak diubah.

**Tech Stack:** Laravel + Inertia + React 18 + TypeScript + Tailwind CSS v4.

**Spec acuan:** `docs/superpowers/specs/2026-08-14-shopping-mobile-uiux-design.md`

## Global Constraints

- **Logika tidak diubah**: handleScan (+1 tanpa clamp), filter, handleSubmit, footer sticky, Alert feedback, empty state.
- **Dark mode wajib**: semua elemen baru menyertakan `dark:` kelas.
- **Target sentuh min 44px** di elemen interaktif baru.
- **Tidak ada commit**: user yang commit sendiri. JANGAN jalankan `git commit`/`git add`.
- **Tidak ada test framework frontend** (keputusan project): verifikasi = `npm run build` + checklist visual.
- Breakpoint dual rendering: `md` (768px) — `< md` = kartu, `≥ md` = tabel.
- Warning overstock, badge footer, tombol "Tidak Bisa Diproses" **tetap dipertahankan** (jangan dihapus).
- `QtyStepper` (`resources/js/Components/QtyStepper.tsx`) dipakai tanpa perubahan.

---

### Task 1: Create.tsx — Barang Dipilih: stepper + kartu mobile

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Create.tsx`

**Interfaces:**
- Consumes: `QtyStepper` (`value`, `onChange`, `max`); `updateItem(productId, rackId, 'quantity', n)` (sudah ada).

- [ ] **Step 1: Import QtyStepper**

Setelah baris `import EmptyState from '../../../Tailadmin/components/common/EmptyState';` tambahkan:

```tsx
import QtyStepper from '../../../Components/QtyStepper';
```

- [ ] **Step 2: Ganti cabang else Barang Dipilih (tabel → dual rendering)**

Cari blok berikut (dari `) : (` setelah EmptyState sampai penutup `)}` sebelum `</ComponentCard>`):

```tsx
                        ) : (
                            <div className="overflow-x-auto max-h-60 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {activeItems.map((item, idx) => {
                                            const isOver = item.quantity > item.stock;
                                            return (
                                            <tr key={`active-${item.product_id}-${item.rack_id}`} className={isOver ? 'bg-red-50' : 'bg-blue-50/50'}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5 w-24">
                                                    <input type="text" inputMode="numeric"
                                                        value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className={`w-full max-w-[5.5rem] h-9 px-1 text-center text-sm tabular-nums border rounded ${isOver ? 'border-red-400 bg-red-50' : 'border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white'}`}
                                                    />
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="text-red-400 hover:text-red-600 text-sm"
                                                    >✕</button>
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
```

Ganti dengan:

```tsx
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto max-h-60 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {activeItems.map((item, idx) => {
                                            const isOver = item.quantity > item.stock;
                                            return (
                                            <tr key={`active-${item.product_id}-${item.rack_id}`} className={isOver ? 'bg-red-50' : 'bg-blue-50/50'}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                        max={item.stock}
                                                    />
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-400 hover:text-red-600 text-lg"
                                                    >✕</button>
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu */}
                            <div className="md:hidden space-y-3">
                                {activeItems.map((item) => {
                                    const isOver = item.quantity > item.stock;
                                    return (
                                        <div key={`active-m-${item.product_id}-${item.rack_id}`} className={`rounded-xl border p-4 ${isOver ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-blue-50/50 dark:bg-gray-900'}`}>
                                            <div className="flex justify-between items-start gap-2 mb-3">
                                                <div>
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                    className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-400 hover:text-red-600 text-lg"
                                                    title="Hapus"
                                                >✕</button>
                                            </div>
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="text-sm text-gray-600 dark:text-gray-300">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : <span>{item.rack_label} <span className="text-gray-400">({item.rack_zone})</span></span>}
                                                </div>
                                                <QtyStepper
                                                    value={item.quantity}
                                                    onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                    max={item.stock}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            </>
                        )}
```

- [ ] **Step 3: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 2: Create.tsx — Cari Produk: stepper + kartu mobile

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Create.tsx`

**Interfaces:**
- Consumes: `QtyStepper` (Task 1); `filteredItems`, `updateItem` (sudah ada).

- [ ] **Step 1: Ganti cabang else tabel Cari Produk dengan dual rendering**

Cari blok berikut (cabang terakhir ternary filter — dari `) : (` setelah "Tidak ada produk ditemukan" sampai `)}` sebelum info "Menampilkan"):

```tsx
                        ) : (
                            <div className="overflow-x-auto max-h-80 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredItems.map((item, idx) => (
                                            <tr key={`${item.product_id}-${item.rack_id}`} className={item.quantity > 0 ? 'bg-blue-50' : ''}>
                                                <td className="px-4 py-2.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-4 py-2.5 text-sm">{item.name}</td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : <span>{item.rack_label} <span className="text-gray-400">({item.rack_zone})</span></span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-center tabular-nums">{item.stock}</td>
                                                <td className="px-4 py-2.5 w-24">
                                                    <input type="text" inputMode="numeric"
                                                        value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="w-full max-w-[5.5rem] h-9 px-1 text-center text-sm tabular-nums border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
```

Ganti dengan:

```tsx
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto max-h-80 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredItems.map((item, idx) => (
                                            <tr key={`${item.product_id}-${item.rack_id}`} className={item.quantity > 0 ? 'bg-blue-50' : ''}>
                                                <td className="px-4 py-2.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-4 py-2.5 text-sm">{item.name}</td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : <span>{item.rack_label} <span className="text-gray-400">({item.rack_zone})</span></span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-center tabular-nums">{item.stock}</td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                        max={item.stock}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu */}
                            <div className="md:hidden space-y-3">
                                {filteredItems.map((item) => (
                                    <div key={`m-${item.product_id}-${item.rack_id}`} className={`rounded-xl border p-4 ${item.quantity > 0 ? 'border-blue-200 bg-blue-50 dark:bg-blue-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-white dark:bg-gray-900'}`}>
                                        <div className="flex justify-between items-start gap-2 mb-3">
                                            <div>
                                                <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                            </div>
                                            <span className="h-fit whitespace-nowrap px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                Stok: {item.stock}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : <span>{item.rack_label} <span className="text-gray-400">({item.rack_zone})</span></span>}
                                            </div>
                                            <QtyStepper
                                                value={item.quantity}
                                                onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                max={item.stock}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                            </>
                        )}
```

- [ ] **Step 2: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 3: Edit.tsx — mirror Task 1 & 2

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Edit.tsx`

**Interfaces:**
- Consumes: `QtyStepper`; `updateItem`, `activeItems`, `filteredItems` (sudah ada di Edit).

- [ ] **Step 1: Import QtyStepper**

Setelah baris `import EmptyState from '../../../Tailadmin/components/common/EmptyState';` tambahkan:

```tsx
import QtyStepper from '../../../Components/QtyStepper';
```

- [ ] **Step 2: Barang Dipilih — ganti cabang else tabel**

Cari blok berikut di `Edit.tsx`:

```tsx
                        ) : (
                            <div className="overflow-x-auto max-h-60 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {activeItems.map((item) => {
                                            const isOver = item.quantity > item.stock;
                                            return (
                                            <tr key={`active-${item.product_id}-${item.rack_id}`} className={isOver ? 'bg-red-50' : 'bg-blue-50/50'}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5 w-24">
                                                    <input type="text" inputMode="numeric" value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className={`w-full max-w-[5.5rem] h-9 px-1 text-center text-sm tabular-nums border rounded ${isOver ? 'border-red-400 bg-red-50' : 'border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white'}`} />
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="text-red-400 hover:text-red-600 text-sm">✕</button>
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
```

Ganti dengan:

```tsx
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto max-h-60 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {activeItems.map((item) => {
                                            const isOver = item.quantity > item.stock;
                                            return (
                                            <tr key={`active-${item.product_id}-${item.rack_id}`} className={isOver ? 'bg-red-50' : 'bg-blue-50/50'}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                        max={item.stock}
                                                    />
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-400 hover:text-red-600 text-lg"
                                                    >✕</button>
                                                </td>
                                            </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu */}
                            <div className="md:hidden space-y-3">
                                {activeItems.map((item) => {
                                    const isOver = item.quantity > item.stock;
                                    return (
                                        <div key={`active-m-${item.product_id}-${item.rack_id}`} className={`rounded-xl border p-4 ${isOver ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-blue-50/50 dark:bg-gray-900'}`}>
                                            <div className="flex justify-between items-start gap-2 mb-3">
                                                <div>
                                                    <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                    <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                                    {isOver && <span className="text-[11px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                    className="inline-flex items-center justify-center h-11 w-11 rounded-lg text-red-400 hover:text-red-600 text-lg"
                                                    title="Hapus"
                                                >✕</button>
                                            </div>
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="text-sm text-gray-600 dark:text-gray-300">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </div>
                                                <QtyStepper
                                                    value={item.quantity}
                                                    onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                    max={item.stock}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            </>
                        )}
```

- [ ] **Step 3: Cari Produk — ganti cabang else tabel**

Cari blok berikut di `Edit.tsx`:

```tsx
                        ) : (
                            <div className="overflow-x-auto max-h-80 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredItems.map((item) => (
                                            <tr key={`${item.product_id}-${item.rack_id}`} className={item.quantity > 0 ? 'bg-blue-50' : ''}>
                                                <td className="px-4 py-2.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-4 py-2.5 text-sm">{item.name}</td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-center tabular-nums">{item.stock}</td>
                                                <td className="px-4 py-2.5 w-24">
                                                    <input type="text" inputMode="numeric" value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="w-full max-w-[5.5rem] h-9 px-1 text-center text-sm tabular-nums border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
```

Ganti dengan:

```tsx
                        ) : (
                            <>
                            {/* Desktop (≥ md): tabel */}
                            <div className="hidden md:block overflow-x-auto max-h-80 overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-48">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {filteredItems.map((item) => (
                                            <tr key={`${item.product_id}-${item.rack_id}`} className={item.quantity > 0 ? 'bg-blue-50' : ''}>
                                                <td className="px-4 py-2.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-4 py-2.5 text-sm">{item.name}</td>
                                                <td className="px-4 py-2.5 text-sm">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-4 py-2.5 text-sm text-center tabular-nums">{item.stock}</td>
                                                <td className="px-4 py-2.5">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                        max={item.stock}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile (< md): kartu */}
                            <div className="md:hidden space-y-3">
                                {filteredItems.map((item) => (
                                    <div key={`m-${item.product_id}-${item.rack_id}`} className={`rounded-xl border p-4 ${item.quantity > 0 ? 'border-blue-200 bg-blue-50 dark:bg-blue-900/10' : 'border-[#E9ECEF] dark:border-gray-700 bg-white dark:bg-gray-900'}`}>
                                        <div className="flex justify-between items-start gap-2 mb-3">
                                            <div>
                                                <div className="font-mono text-xs text-gray-500 dark:text-gray-400">{item.part_number}</div>
                                                <div className="text-sm font-medium text-gray-800 dark:text-gray-200">{item.name}</div>
                                            </div>
                                            <span className="h-fit whitespace-nowrap px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                Stok: {item.stock}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                            </div>
                                            <QtyStepper
                                                value={item.quantity}
                                                onChange={(n) => updateItem(item.product_id, item.rack_id, 'quantity', n)}
                                                max={item.stock}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                            </>
                        )}
```

- [ ] **Step 4: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 4: Index.tsx — sentuhan ringan mobile

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Index.tsx`

**Interfaces:**
- Consumes: tidak ada komponen baru.

- [ ] **Step 1: Filter full-width di HP**

Cari:

```tsx
                        <div className="min-w-[200px]">
                            <Label>Cari Lokasi</Label>
```

Ganti dengan:

```tsx
                        <div className="w-full sm:min-w-[200px]">
                            <Label>Cari Lokasi</Label>
```

Cari:

```tsx
                        <div className="min-w-[160px]">
                            <Label>Status</Label>
```

Ganti dengan:

```tsx
                        <div className="w-full sm:min-w-[160px]">
                            <Label>Status</Label>
```

- [ ] **Step 2: Perbesar tombol edit & hapus custom ke 44px**

Cari (tombol edit):

```tsx
                                                        className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/20 transition-colors"
```

Ganti dengan:

```tsx
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/20 transition-colors"
```

Cari (tombol hapus):

```tsx
                                                        className="group relative inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/20 transition-colors"
```

Ganti dengan:

```tsx
                                                        className="group relative inline-flex items-center justify-center w-11 h-11 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/20 transition-colors"
```

Kedua SVG di dalam tombol tersebut: ubah `className="w-4 h-4"` menjadi `className="w-5 h-5"` (ada 2 kemunculan `w-4 h-4` di file ini — gunakan replace all untuk baris `<svg xmlns="http://www.w3.org/2000/svg" className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>`).

- [ ] **Step 3: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error.

---

### Task 5: Verifikasi akhir keseluruhan

**Files:** (tidak ada perubahan kode)

- [ ] **Step 1: Build bersih**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error/warning baru.

- [ ] **Step 2: Cek visual HP (390px) — DevTools responsive**

- `shoppings/create`: urutan Detail → Barang Dipilih → Cari Produk; kedua daftar tampil sebagai **kartu** (part#, nama, badge Stok, stepper −/+, rak, tombol ✕ di Barang Dipilih); footer sticky `X jenis • Y qty` + tombol tetap terlihat.
- Stepper: + disabled di stok; − disabled di 0; ketik manual ter-clamp ke stok; scan part tetap +1 (boleh melewati stok, lalu tombol + disabled).
- `shoppings/{id}/edit`: sama seperti Create.
- `shoppings` (index): filter full-width di HP; tombol aksi 44px.

- [ ] **Step 3: Cek tablet (768px) & desktop (1280px)**

- ≥768px: kembali ke tabel (Barang Dipilih & Cari Produk) dengan QtyStepper; kolom Qty melebar (w-48).

- [ ] **Step 4: Cek dark mode**

- Kartu item, stepper, highlight biru/merah terbaca dengan kelas `dark:`.

- [ ] **Step 5: Cek perilaku yang tidak berubah**

- Scan part +1 & auto-reopen scanner; scan frame & lokasi tetap bekerja; filter + Reset; footer sticky; Alert feedback; konfirmasi submit tunggal.
