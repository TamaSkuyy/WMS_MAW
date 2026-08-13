# Shopping UI/UX Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merapikan UI/UX modul Shopping (Create/Edit/Index/Show) — layout seimbang 5/12–7/12, footer sticky, tabel & input qty lebih besar, filter lega, konsistensi komponen — tanpa mengubah logika bisnis.

**Architecture:** Murni perubahan presentasi React/Tailwind pada 4 file halaman Shopping. Menggunakan komponen bawaan Tailadmin: `Badge`, `Alert`, `EmptyState`, `ComponentCard`, `Input` (dengan `selectOnFocus`), `SearchableSelect`, `Button`.

**Tech Stack:** Laravel + Inertia + React 18 + TypeScript + Tailwind CSS v4.

**Spec acuan:** `docs/superpowers/specs/2026-08-13-shopping-uiux-design.md`

## Global Constraints

- **Logika tidak diubah**: scan, filter, validasi overstock, payload submit, state management tetap seperti sekarang. Hanya JSX/className.
- **Dark mode wajib terjaga**: setiap elemen yang diubah/dipindah harus menyertakan kelas `dark:` seperti pola yang ada.
- **Label status Bahasa Indonesia**: `draft` → "Draft", `shipped` → "Dikirim", `completed` → "Selesai".
- **Tidak ada commit**: user yang commit sendiri. JANGAN jalankan `git commit`/`git add`.
- **Tidak ada test framework frontend** (keputusan user): verifikasi tiap task = `npm run build` sukses + checklist visual.
- **Perilaku input qty yang sudah ada** (`onFocus` select-all di Create/Edit) harus dipertahankan pada semua input qty yang diubah.
- Path impor komponen dari `resources/js/Pages/Transactions/Shopping/`: `../../../Tailadmin/components/ui/badge/Badge`, `../../../Tailadmin/components/ui/alert/Alert`, `../../../Tailadmin/components/common/EmptyState`, `../../../Tailadmin/components/form/Label`.
- Deviasi kecil dari spec yang disadari: komponen `Alert` hanya punya 4 varian (success/warning/error/info), sehingga pemetaan feedback scan: `ok` → success (hijau), `no_stock` → warning (kuning-oranye), `unknown` → error (merah). Pemetaan ini konsisten di Create & Edit.

---

### Task 1: Create.tsx — Restrukturisasi layout + sticky footer

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Create.tsx`

**Interfaces:**
- Consumes: `Badge` (`ui/badge/Badge.tsx`, props: `color: "light"|"success"|"error"|"warning"|"info"|"primary"|"dark"`, `variant: "light"|"solid"`, `size: "sm"|"md"`); `CheckIcon` dari `@heroicons/react/24/outline` (sudah diimpor).
- Produces: struktur layout baru yang menjadi acuan Task 2–4.

- [ ] **Step 1: Tambah import Badge**

Di blok import `Create.tsx`, setelah baris `import QrScanner from '../../../Components/QrScanner';` tambahkan:

```tsx
import Badge from '../../../Tailadmin/components/ui/badge/Badge';
```

- [ ] **Step 2: Ubah pembungkus grid ke 12 kolom**

Cari:

```tsx
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
```

Ganti dengan:

```tsx
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
```

- [ ] **Step 3: Buka div kolom kiri (span-5) SEBELUM kartu Detail Shopping**

Cari:

```tsx
                    <ComponentCard
                        title="Detail Shopping"
                        action={
```

Ganti dengan:

```tsx
                    <div className="xl:col-span-5 flex flex-col gap-6">
                    <ComponentCard
                        title="Detail Shopping"
                        action={
```

- [ ] **Step 4: Hapus pembuka div flex-col lama (antara Detail dan Barang Dipilih)**

Cari:

```tsx
                    <div className="flex flex-col gap-6">
                    <ComponentCard
                        title={`Barang Dipilih (${activeItems.length} jenis, ${activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty)`}
```

Ganti dengan (hapus baris div, kartu Barang Dipilih langsung masuk kolom kiri):

```tsx
                    <ComponentCard
                        title={`Barang Dipilih (${activeItems.length} jenis, ${activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty)`}
```

- [ ] **Step 5: Tutup kolom kiri + buka kolom kanan (span-7) sebelum kartu Cari Produk**

Cari:

```tsx
                    <ComponentCard title="Cari Produk" desc="Gunakan filter lalu isi qty">
```

Ganti dengan:

```tsx
                    </div>

                    <div className="xl:col-span-7">
                    <ComponentCard title="Cari Produk" desc="Gunakan filter lalu isi qty">
```

- [ ] **Step 6: Tutup grid + tambah sticky footer, hapus area submit lama**

Cari bagian akhir (dari info "Menampilkan" sampai `</form>`, INKLUSIF — supaya tidak ada tag sisa):

```tsx
                        <div className="pt-2 text-xs text-gray-400">Menampilkan {filteredItems.length} dari {tableItems.length} produk</div>
                    </ComponentCard>
                    </div>

                    <div className="flex flex-col gap-2 items-end">
                        {hasOverStock && (
                            <p className="text-sm text-red-600 font-medium">
                                ⚠️ {overStockItems.length} barang melebihi stok tersedia — kurangi qty atau tidak bisa diproses
                            </p>
                        )}
                        <Button onClick={handleSubmit} disabled={!canSubmit || submitting} size="lg" icon={<CheckIcon className="w-4 h-4" />}>
                            {submitting ? 'Menyimpan...' : hasOverStock ? 'Tidak Bisa Diproses' : activeItems.length > 0 ? 'Proses Shopping' : 'Simpan Header'}
                        </Button>
                    </div>
                </div>
            </form>
```

Ganti dengan:

```tsx
                        <div className="pt-2 text-xs text-gray-400">Menampilkan {filteredItems.length} dari {tableItems.length} produk</div>
                    </ComponentCard>
                    </div>
                </div>

                {/* Sticky action footer — di luar grid, tetap di dalam <form> */}
                <div className="sticky bottom-4 z-10 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#E9ECEF] bg-white px-5 py-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <span className="font-medium text-[#1A1D23] dark:text-white">
                            {activeItems.length} jenis &bull; {activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty
                        </span>
                        {hasOverStock && (
                            <Badge color="error" variant="light" size="sm">
                                ⚠️ {overStockItems.length} barang melebihi stok
                            </Badge>
                        )}
                    </div>
                    <Button type="button" onClick={handleSubmit} disabled={!canSubmit || submitting} icon={<CheckIcon className="w-4 h-4" />}>
                        {submitting ? 'Menyimpan...' : hasOverStock ? 'Tidak Bisa Diproses' : activeItems.length > 0 ? 'Proses Shopping' : 'Simpan Header'}
                    </Button>
                </div>
            </form>
```

Hasil struktur (Create.tsx): `form > div.grid > [div.col-span-5: Detail + Barang Dipilih][div.col-span-7: Cari Produk]` + footer di luar grid di dalam form. Pada < xl (grid-cols-1), urutan stack: Detail → Barang Dipilih → Cari Produk.

Catatan penting: tombol footer memakai `type="button"` + `onClick` (satu jalur submit saja). Tombol lama tanpa `type` default-nya `submit` di dalam `<form onSubmit={handleSubmit}>`, sehingga klik memicu onClick DAN onSubmit (konfirmasi ganda). Perilaku baru: satu konfirmasi, satu submit.

- [ ] **Step 7: Verifikasi build**

Run: `npm run build`
Expected: keluar `✓ built in ...` tanpa error; halaman Create tetap ter-render (cek via dev server `npm run dev`).

---

### Task 2: Create.tsx — Tabel, filter, input qty, Alert & EmptyState

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Create.tsx`

**Interfaces:**
- Consumes: struktur dari Task 1; `Alert` (`ui/alert/Alert.tsx`, props: `variant: "success"|"error"|"warning"|"info"`, `title: string`, `message: string`); `EmptyState` (`common/EmptyState.tsx`, props: `icon?`, `title`, `message?`); `Label` (`form/Label.tsx`, children = teks label).

- [ ] **Step 1: Import Alert, EmptyState, Label**

Di blok import, setelah import `Badge` (dari Task 1) tambahkan:

```tsx
import Alert from '../../../Tailadmin/components/ui/alert/Alert';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Label from '../../../Tailadmin/components/form/Label';
```

- [ ] **Step 2: Ganti feedback scan dengan Alert**

Cari:

```tsx
                            {lastScan && (
                                <div className={`text-xs px-3 py-2 rounded ${
                                    lastScanStatus === 'ok' ? 'bg-green-50 text-green-700' :
                                    lastScanStatus === 'no_stock' ? 'bg-yellow-50 text-yellow-700' :
                                    'bg-orange-50 text-orange-700'
                                }`}>
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}{lastScan}
                                    {lastScanStatus === 'no_stock' && ' — Stok habis'}
                                    {lastScanStatus === 'unknown' && ' — Part tidak dikenal'}
                                </div>
                            )}
```

Ganti dengan:

```tsx
                            {lastScan && lastScanStatus !== null && (
                                <Alert
                                    variant={lastScanStatus === 'ok' ? 'success' : lastScanStatus === 'no_stock' ? 'warning' : 'error'}
                                    title={lastScanStatus === 'ok' ? 'Scan Berhasil' : lastScanStatus === 'no_stock' ? 'Stok Habis' : 'Part Tidak Dikenal'}
                                    message={lastScan}
                                />
                            )}
```

- [ ] **Step 3: Empty state "Barang Dipilih" pakai komponen EmptyState**

Cari:

```tsx
                        {activeItems.length === 0 ? (
                            <div className="py-6 text-center border-2 border-dashed border-gray-200 rounded-lg">
                                <p className="text-3xl mb-2">📦</p>
                                <p className="text-sm text-gray-500">Belum ada barang dipilih</p>
                                <p className="text-xs text-gray-400 mt-1">Scan QR atau cari produk di bawah</p>
                            </div>
                        ) : (
```

Ganti dengan:

```tsx
                        {activeItems.length === 0 ? (
                            <EmptyState icon="📦" title="Belum ada barang dipilih" message="Scan QR atau isi qty di tabel Cari Produk." />
                        ) : (
```

- [ ] **Step 4: Header tabel Barang Dipilih — gabung kolom Part# + Produk**

Cari header tabel aktif:

```tsx
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Qty</th>
                                            <th className="px-3 py-2 w-8"></th>
                                        </tr>
```

Ganti dengan:

```tsx
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
```

- [ ] **Step 5: Sel data tabel Barang Dipilih — gabung sel, spasi lega**

Cari:

```tsx
                                                <td className="px-3 py-1.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.name}
                                                    {isOver && <span className="ml-1 text-[10px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-1 py-1.5 w-24">
                                                    <input type="text" inputMode="numeric"
                                                        value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className={`w-full px-1 py-1.5 text-center text-xs border rounded ${isOver ? 'border-red-400 bg-red-50' : ''}`}
                                                    />
                                                </td>
                                                <td className="px-1 py-1.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="text-red-400 hover:text-red-600 text-xs">✕</button>
                                                </td>
```

Ganti dengan:

```tsx
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
                                                        className="text-red-400 hover:text-red-600 text-sm">✕</button>
                                                </td>
```

- [ ] **Step 6: Filter Cari Produk jadi dua baris berlabel**

Cari blok filter lama:

```tsx
                        <div className="mb-3 flex flex-wrap gap-3 items-end">
                            <div className="flex-1 min-w-[160px]">
                                <Input type="text" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari part number / nama..." />
                            </div>
                            <div className="w-40">
                                <SearchableSelect
                                    options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                    value={filterSupplierId} onChange={(v) => setFilterSupplierId(v as string)} placeholder="Supplier..." />
                            </div>
                            <div className="w-48">
                                <SearchableSelect
                                    options={vehicleModels.map((v: any) => ({ value: v.id, label: v.label }))}
                                    value={filterVehicleModelId} onChange={(v) => setFilterVehicleModelId(v as string)} placeholder="Model..." />
                            </div>
                            {(searchQuery || filterSupplierId || filterVehicleModelId) && (
                                <button type="button" onClick={() => { setSearchQuery(''); setFilterSupplierId(''); setFilterVehicleModelId(''); }} className="text-xs text-red-500 hover:text-red-700 px-2 py-1">✕ Reset</button>
                            )}
                        </div>
```

Ganti dengan:

```tsx
                        <div className="mb-4 space-y-3">
                            <div>
                                <Label>Cari Produk</Label>
                                <Input type="text" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari part number / nama..." />
                            </div>
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="w-full sm:w-48">
                                    <Label>Supplier</Label>
                                    <SearchableSelect
                                        options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                        value={filterSupplierId} onChange={(v) => setFilterSupplierId(v as string)} placeholder="Semua supplier" />
                                </div>
                                <div className="w-full sm:w-56">
                                    <Label>Model</Label>
                                    <SearchableSelect
                                        options={vehicleModels.map((v: any) => ({ value: v.id, label: v.label }))}
                                        value={filterVehicleModelId} onChange={(v) => setFilterVehicleModelId(v as string)} placeholder="Semua model" />
                                </div>
                                {(searchQuery || filterSupplierId || filterVehicleModelId) && (
                                    <button type="button" onClick={() => { setSearchQuery(''); setFilterSupplierId(''); setFilterVehicleModelId(''); }} className="mb-1 text-sm text-red-500 hover:text-red-700">✕ Reset</button>
                                )}
                            </div>
                        </div>
```

- [ ] **Step 7: Tabel Cari Produk — header lebih lega**

Cari:

```tsx
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Qty</th>
                                        </tr>
```

Ganti dengan:

```tsx
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                        </tr>
```

- [ ] **Step 8: Sel data tabel Cari Produk — spasi lega, qty besar**

Cari:

```tsx
                                                <td className="px-3 py-1.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-3 py-1.5 text-xs">{item.name}</td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : <span>{item.rack_label} <span className="text-gray-400">({item.rack_zone})</span></span>}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs">{item.stock}</td>
                                                <td className="px-1 py-1.5 w-24">
                                                    <input type="text" inputMode="numeric"
                                                        value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="w-full px-1 py-1.5 text-center text-xs border rounded"
                                                    />
                                                </td>
```

Ganti dengan:

```tsx
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
```

- [ ] **Step 9: Verifikasi build**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error. Cek visual: filter 2 baris berlabel, input qty besar, Alert scan muncul saat scan, EmptyState tampil saat belum ada barang.

---

### Task 3: Edit.tsx — Restrukturisasi layout + sticky footer

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Edit.tsx`

**Interfaces:**
- Consumes: `Badge` (lihat Task 1). Struktur Edit identik dengan Create; perbedaan hanya teks tombol submit: `'Simpan Perubahan'` (bukan `'Proses Shopping'`).

- [ ] **Step 1: Tambah import Badge**

Di blok import `Edit.tsx`, setelah baris `import QrScanner from '../../../Components/QrScanner';` tambahkan:

```tsx
import Badge from '../../../Tailadmin/components/ui/badge/Badge';
```

- [ ] **Step 2: Ubah pembungkus grid ke 12 kolom**

Cari:

```tsx
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
```

Ganti dengan:

```tsx
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
```

- [ ] **Step 3: Buka div kolom kiri (span-5) SEBELUM kartu Detail Shopping**

Cari:

```tsx
                    <ComponentCard
                        title="Detail Shopping"
                        action={
```

Ganti dengan:

```tsx
                    <div className="xl:col-span-5 flex flex-col gap-6">
                    <ComponentCard
                        title="Detail Shopping"
                        action={
```

- [ ] **Step 4: Hapus pembuka div flex-col lama (antara Detail dan Barang Dipilih)**

Cari:

```tsx
                    <div className="flex flex-col gap-6">
                    <ComponentCard
                        title={`Barang Dipilih (${activeItems.length} jenis, ${activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty)`}
```

Ganti dengan (hapus baris div, kartu Barang Dipilih langsung masuk kolom kiri):

```tsx
                    <ComponentCard
                        title={`Barang Dipilih (${activeItems.length} jenis, ${activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty)`}
```

- [ ] **Step 5: Tutup kolom kiri + buka kolom kanan (span-7) sebelum kartu Cari Produk**

Cari:

```tsx
                    <ComponentCard title="Cari Produk" desc="Gunakan filter lalu isi qty">
```

Ganti dengan:

```tsx
                    </div>

                    <div className="xl:col-span-7">
                    <ComponentCard title="Cari Produk" desc="Gunakan filter lalu isi qty">
```

- [ ] **Step 6: Tutup grid + tambah sticky footer, hapus area submit lama**

Cari bagian akhir (dari info "Menampilkan" sampai `</form>`, INKLUSIF — supaya tidak ada tag sisa):

```tsx
                        <div className="pt-2 text-xs text-gray-400">Menampilkan {filteredItems.length} dari {tableItems.length} produk</div>
                    </ComponentCard>
                    </div>

                    <div className="flex flex-col gap-2 items-end">
                        {hasOverStock && (
                            <p className="text-sm text-red-600 font-medium">
                                ⚠️ {overStockItems.length} barang melebihi stok tersedia — kurangi qty atau tidak bisa diproses
                            </p>
                        )}
                        <Button onClick={handleSubmit} disabled={!canSubmit || submitting} icon={<CheckIcon className="w-4 h-4" />}>
                            {submitting ? 'Menyimpan...' : hasOverStock ? 'Tidak Bisa Diproses' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </div>
            </form>
```

Ganti dengan:

```tsx
                        <div className="pt-2 text-xs text-gray-400">Menampilkan {filteredItems.length} dari {tableItems.length} produk</div>
                    </ComponentCard>
                    </div>
                </div>

                {/* Sticky action footer — di luar grid, tetap di dalam <form> */}
                <div className="sticky bottom-4 z-10 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#E9ECEF] bg-white px-5 py-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <span className="font-medium text-[#1A1D23] dark:text-white">
                            {activeItems.length} jenis &bull; {activeItems.reduce((s: number, i: any) => s + i.quantity, 0)} qty
                        </span>
                        {hasOverStock && (
                            <Badge color="error" variant="light" size="sm">
                                ⚠️ {overStockItems.length} barang melebihi stok
                            </Badge>
                        )}
                    </div>
                    <Button type="button" onClick={handleSubmit} disabled={!canSubmit || submitting} icon={<CheckIcon className="w-4 h-4" />}>
                        {submitting ? 'Menyimpan...' : hasOverStock ? 'Tidak Bisa Diproses' : 'Simpan Perubahan'}
                    </Button>
                </div>
            </form>
```

Catatan penting: sama seperti Task 1 Step 5 — `type="button"` mencegah konfirmasi/submit ganda.

- [ ] **Step 7: Verifikasi build**

Run: `npm run build`
Expected: sukses tanpa error.

---

### Task 4: Edit.tsx — Tabel, filter, input qty, Alert & EmptyState

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Edit.tsx`

**Interfaces:**
- Consumes: `Alert`, `EmptyState`, `Label` (lihat Task 2).

- [ ] **Step 1: Import Alert, EmptyState, Label**

Di blok import, setelah import `Badge` (dari Task 3) tambahkan:

```tsx
import Alert from '../../../Tailadmin/components/ui/alert/Alert';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import Label from '../../../Tailadmin/components/form/Label';
```

- [ ] **Step 2: Ganti feedback scan dengan Alert**

Cari:

```tsx
                            {lastScan && (
                                <div className={`text-xs px-3 py-2 rounded ${
                                    lastScanStatus === 'ok' ? 'bg-green-50 text-green-700' :
                                    lastScanStatus === 'no_stock' ? 'bg-yellow-50 text-yellow-700' :
                                    'bg-orange-50 text-orange-700'
                                }`}>
                                    {lastScanStatus === 'ok' ? '✓ ' : '✗ '}{lastScan}
                                    {lastScanStatus === 'no_stock' && ' — Stok habis'}
                                    {lastScanStatus === 'unknown' && ' — Part tidak dikenal'}
                                </div>
                            )}
```

Ganti dengan:

```tsx
                            {lastScan && lastScanStatus !== null && (
                                <Alert
                                    variant={lastScanStatus === 'ok' ? 'success' : lastScanStatus === 'no_stock' ? 'warning' : 'error'}
                                    title={lastScanStatus === 'ok' ? 'Scan Berhasil' : lastScanStatus === 'no_stock' ? 'Stok Habis' : 'Part Tidak Dikenal'}
                                    message={lastScan}
                                />
                            )}
```

- [ ] **Step 3: Empty state "Barang Dipilih" pakai komponen EmptyState**

Cari:

```tsx
                        {activeItems.length === 0 ? (
                            <div className="py-6 text-center border-2 border-dashed border-gray-200 rounded-lg">
                                <p className="text-3xl mb-2">📦</p>
                                <p className="text-sm text-gray-500">Belum ada barang dipilih</p>
                                <p className="text-xs text-gray-400 mt-1">Scan QR atau cari produk di bawah</p>
                            </div>
                        ) : (
```

Ganti dengan:

```tsx
                        {activeItems.length === 0 ? (
                            <EmptyState icon="📦" title="Belum ada barang dipilih" message="Scan QR atau isi qty di tabel Cari Produk." />
                        ) : (
```

- [ ] **Step 4: Header tabel Barang Dipilih — gabung kolom Part# + Produk**

Cari:

```tsx
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Qty</th>
                                            <th className="px-3 py-2 w-8"></th>
                                        </tr>
```

Ganti dengan:

```tsx
                                        <tr>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                            <th className="px-4 py-2 w-8"></th>
                                        </tr>
```

- [ ] **Step 5: Sel data tabel Barang Dipilih — gabung sel, spasi lega**

Cari:

```tsx
                                                <td className="px-3 py-1.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.name}
                                                    {isOver && <span className="ml-1 text-[10px] text-red-600 font-medium">⚠ Stok hanya {item.stock}</span>}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-1 py-1.5 w-24">
                                                    <input type="text" inputMode="numeric" value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className={`w-full px-1 py-1.5 text-center text-xs border rounded ${isOver ? 'border-red-400 bg-red-50' : ''}`} />
                                                </td>
                                                <td className="px-1 py-1.5">
                                                    <button type="button"
                                                        onClick={() => updateItem(item.product_id, item.rack_id, 'quantity', 0)}
                                                        className="text-red-400 hover:text-red-600 text-xs">✕</button>
                                                </td>
```

Ganti dengan:

```tsx
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
```

- [ ] **Step 6: Filter Cari Produk jadi dua baris berlabel**

Cari:

```tsx
                        <div className="mb-3 flex flex-wrap gap-3 items-end">
                            <div className="flex-1 min-w-[160px]">
                                <Input type="text" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari part number / nama..." />
                            </div>
                            <div className="w-40">
                                <SearchableSelect
                                    options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                    value={filterSupplierId} onChange={(v) => setFilterSupplierId(v as string)} placeholder="Supplier..." />
                            </div>
                            <div className="w-48">
                                <SearchableSelect
                                    options={vehicleModels.map((v: any) => ({ value: v.id, label: v.label }))}
                                    value={filterVehicleModelId} onChange={(v) => setFilterVehicleModelId(v as string)} placeholder="Model..." />
                            </div>
                            {(searchQuery || filterSupplierId || filterVehicleModelId) && (
                                <button type="button" onClick={() => { setSearchQuery(''); setFilterSupplierId(''); setFilterVehicleModelId(''); }} className="text-xs text-red-500 hover:text-red-700 px-2 py-1">✕ Reset</button>
                            )}
                        </div>
```

Ganti dengan:

```tsx
                        <div className="mb-4 space-y-3">
                            <div>
                                <Label>Cari Produk</Label>
                                <Input type="text" value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} placeholder="Cari part number / nama..." />
                            </div>
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="w-full sm:w-48">
                                    <Label>Supplier</Label>
                                    <SearchableSelect
                                        options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                        value={filterSupplierId} onChange={(v) => setFilterSupplierId(v as string)} placeholder="Semua supplier" />
                                </div>
                                <div className="w-full sm:w-56">
                                    <Label>Model</Label>
                                    <SearchableSelect
                                        options={vehicleModels.map((v: any) => ({ value: v.id, label: v.label }))}
                                        value={filterVehicleModelId} onChange={(v) => setFilterVehicleModelId(v as string)} placeholder="Semua model" />
                                </div>
                                {(searchQuery || filterSupplierId || filterVehicleModelId) && (
                                    <button type="button" onClick={() => { setSearchQuery(''); setFilterSupplierId(''); setFilterVehicleModelId(''); }} className="mb-1 text-sm text-red-500 hover:text-red-700">✕ Reset</button>
                                )}
                            </div>
                        </div>
```

- [ ] **Step 7: Tabel Cari Produk — header lebih lega**

Cari:

```tsx
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stok</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Qty</th>
                                        </tr>
```

Ganti dengan:

```tsx
                                        <tr>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                            <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok</th>
                                            <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider w-24">Qty</th>
                                        </tr>
```

- [ ] **Step 8: Sel data tabel Cari Produk — spasi lega, qty besar**

Cari:

```tsx
                                                <td className="px-3 py-1.5 text-xs font-mono">{item.part_number}</td>
                                                <td className="px-3 py-1.5 text-xs">{item.name}</td>
                                                <td className="px-3 py-1.5 text-xs">
                                                    {item.is_relay ? <span className="text-amber-600">⚠ Relay</span> : item.rack_label}
                                                </td>
                                                <td className="px-3 py-1.5 text-xs">{item.stock}</td>
                                                <td className="px-1 py-1.5 w-24">
                                                    <input type="text" inputMode="numeric" value={item.quantity || ''}
                                                        onFocus={(e) => e.target.select()}
                                                        onChange={(e) => updateItem(item.product_id, item.rack_id, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="w-full px-1 py-1.5 text-center text-xs border rounded" />
                                                </td>
```

Ganti dengan:

```tsx
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
```

- [ ] **Step 9: Verifikasi build**

Run: `npm run build`
Expected: sukses tanpa error. Cek visual Edit (buka sebuah shopping draft): layout seimbang, footer sticky, tabel lega.

---

### Task 5: Index.tsx — Header filter menyatu + Badge status Indonesia

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Index.tsx`

**Interfaces:**
- Consumes: `Badge` (lihat Task 1); `Label` (lihat Task 2).

- [ ] **Step 1: Import Badge & Label, ganti map statusColors**

Tambahkan import setelah baris `import EmptyState from '../../../Tailadmin/components/common/EmptyState';`:

```tsx
import Badge from '../../../Tailadmin/components/ui/badge/Badge';
import Label from '../../../Tailadmin/components/form/Label';
```

Cari:

```tsx
    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };
```

Ganti dengan:

```tsx
    const statusBadges: Record<string, { color: 'light' | 'info' | 'success'; label: string }> = {
        draft: { color: 'light', label: 'Draft' },
        shipped: { color: 'info', label: 'Dikirim' },
        completed: { color: 'success', label: 'Selesai' },
    };
```

- [ ] **Step 2: Satukan baris filter + tombol Tambah**

Cari:

```tsx
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Cari Lokasi</label>
                        <Input
                            type="text"
                            defaultValue={filters?.search || ''}
                            placeholder="Nama lokasi tujuan..."
                            onChange={(e) => router.get(route('shoppings.index'), { ...filters, search: e.target.value }, { preserveState: true, replace: true })}
                        />
                    </div>
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'shipped', label: 'Dikirim' },
                                { value: 'completed', label: 'Completed' },
                            ]}
                            value={filters?.status || ''}
                            onChange={(v) => router.get(route('shoppings.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                        />
                    </div>
                </div>
                <div className="mb-3">
                    {canCreate && (
                        <Link href={route('shoppings.create')}><Button>Tambah Shopping</Button></Link>
                    )}
                </div>
```

Ganti dengan:

```tsx
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-[200px]">
                            <Label>Cari Lokasi</Label>
                            <Input
                                type="text"
                                defaultValue={filters?.search || ''}
                                placeholder="Nama lokasi tujuan..."
                                onChange={(e) => router.get(route('shoppings.index'), { ...filters, search: e.target.value }, { preserveState: true, replace: true })}
                            />
                        </div>
                        <div className="min-w-[160px]">
                            <Label>Status</Label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'Semua' },
                                    { value: 'draft', label: 'Draft' },
                                    { value: 'shipped', label: 'Dikirim' },
                                    { value: 'completed', label: 'Selesai' },
                                ]}
                                value={filters?.status || ''}
                                onChange={(v) => router.get(route('shoppings.index'), { ...filters, status: v as string }, { preserveState: true, replace: true })}
                            />
                        </div>
                    </div>
                    {canCreate && (
                        <Link href={route('shoppings.create')}><Button>Tambah Shopping</Button></Link>
                    )}
                </div>
```

- [ ] **Step 3: Badge status di tabel**

Cari:

```tsx
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[s.status]}`}>{s.status}</span>
                                    </td>
```

Ganti dengan:

```tsx
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <Badge color={statusBadges[s.status]?.color ?? 'light'} variant="light" size="sm">
                                            {statusBadges[s.status]?.label ?? s.status}
                                        </Badge>
                                    </td>
```

- [ ] **Step 4: Verifikasi build**

Run: `npm run build`
Expected: sukses tanpa error. Cek visual: filter + tombol sejajar, badge status berlabel Indonesia (Draft/Dikirim/Selesai), dark mode terbaca.

---

### Task 6: Show.tsx — Badge status Indonesia + tabel items lega

**Files:**
- Modify: `resources/js/Pages/Transactions/Shopping/Show.tsx`

**Interfaces:**
- Consumes: `Badge` (lihat Task 1).

- [ ] **Step 1: Import Badge, ganti map statusColors**

Tambahkan import setelah baris `import Button from '../../../Tailadmin/components/ui/button/Button';`:

```tsx
import Badge from '../../../Tailadmin/components/ui/badge/Badge';
```

Cari:

```tsx
    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };
```

Ganti dengan:

```tsx
    const statusBadges: Record<string, { color: 'light' | 'info' | 'success'; label: string }> = {
        draft: { color: 'light', label: 'Draft' },
        shipped: { color: 'info', label: 'Dikirim' },
        completed: { color: 'success', label: 'Selesai' },
    };
```

- [ ] **Step 2: Badge status di kartu Info**

Cari:

```tsx
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt><dd><span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[shopping.status]}`}>{shopping.status}</span></dd></div>
```

Ganti dengan:

```tsx
                            <div><dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Status</dt><dd><Badge color={statusBadges[shopping.status]?.color ?? 'light'} variant="light" size="sm">{statusBadges[shopping.status]?.label ?? shopping.status}</Badge></dd></div>
```

- [ ] **Step 3: Tabel items — header lega**

Cari:

```tsx
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Part #</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
```

Ganti dengan:

```tsx
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Part #</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Model</th>
                                        <th className="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rak</th>
                                        <th className="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Qty</th>
```

- [ ] **Step 4: Sel data tabel items — spasi lega**

Cari:

```tsx
                                            <td className="px-3 py-2 text-sm font-mono">{item.product?.part_number}</td>
                                            <td className="px-3 py-2 text-sm">{item.product?.name}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-3 py-2 text-sm font-mono">{item.rack?.code}</td>
                                            <td className="px-3 py-2 text-sm font-medium">{item.quantity}</td>
```

Ganti dengan:

```tsx
                                            <td className="px-4 py-2.5 text-xs font-mono">{item.product?.part_number}</td>
                                            <td className="px-4 py-2.5 text-sm">{item.product?.name}</td>
                                            <td className="px-4 py-2.5 text-sm text-gray-500">{item.product?.vehicle_model?.name || '-'}</td>
                                            <td className="px-4 py-2.5 text-sm font-mono">{item.rack?.code}</td>
                                            <td className="px-4 py-2.5 text-sm font-medium text-center tabular-nums">{item.quantity}</td>
```

- [ ] **Step 5: Verifikasi build**

Run: `npm run build`
Expected: sukses tanpa error.

---

### Task 7: Verifikasi akhir keseluruhan

**Files:** (tidak ada perubahan kode)

- [ ] **Step 1: Build bersih**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error/warning baru.

- [ ] **Step 2: Cek visual desktop (lebar ≥ 1280px)**

Jalankan `npm run dev` dan buka:
- `shoppings/create`: grid kiri 5/12 berisi Detail + Barang Dipilih; kanan 7/12 Cari Produk; footer sticky `X jenis • Y qty` + tombol; filter dua baris berlabel; input qty `h-9` besar; scan QR via tombol → Alert hijau muncul; overstock → badge merah di footer + row merah.
- `shoppings/{id}/edit`: sama seperti Create, tombol bertuliskan "Simpan Perubahan".
- `shoppings` (index): filter + tombol Tambah sejajar; badge Draft/Dikirim/Selesai.
- `shoppings/{id}` (show): badge status Indonesia; tabel items lega.

- [ ] **Step 3: Cek visual tablet (768px) & mobile (390px)**

DevTools responsive mode:
- Urutan kartu: Detail → Barang Dipilih → Cari Produk.
- Footer sticky tetap terlihat di bawah viewport saat scroll.
- Filter Supplier/Model menumpuk full-width di layar sempit; tabel scroll horizontal bila perlu.

- [ ] **Step 4: Cek dark mode**

Toggle dark mode (ThemeToggleButton di header):
- Footer, tabel, input qty, badge, Alert terbaca dengan kelas `dark:`.

- [ ] **Step 5: Cek perilaku yang tidak berubah**

- Scan part dua kali → qty bertambah; auto-reopen scanner bekerja.
- Input qty: klik → teks terseleksi otomatis (perbaikan sebelumnya tetap jalan).
- Overstock memblokir submit (tombol jadi "Tidak Bisa Diproses" + disabled).
- Submit: hanya SATU dialog konfirmasi muncul (perbaikan double-fire dari Task 1/3).
- Filter + Reset bekerja; info "Menampilkan X dari Y produk" tetap tampil.
