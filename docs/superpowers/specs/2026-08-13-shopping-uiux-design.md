# Desain UI/UX Modul Shopping (Create/Edit + konsistensi Index/Show)

- **Tanggal:** 2026-08-13
- **Status:** Disetujui user (3 bagian desain)
- **Cakupan:** `resources/js/Pages/Transactions/Shopping/` (Create, Edit, Index, Show)
- **Pendekatan terpilih:** C — Hybrid seimbang + sticky footer

## Latar Belakang

User merasa halaman modul Shopping kurang rapi, dengan keluhan spesifik di Create/Edit:

1. Layout dua kolom tidak seimbang (kartu Detail pendek di kiri, dua tabel panjang di kanan)
2. Tabel & input qty terlalu kecil (teks 12px, input `w-24 px-1 py-1.5`) — sulit dibaca dan ditekan, terutama di tablet
3. Baris filter berjejal (cari + supplier + model dalam satu baris, label manual tidak konsisten)
4. Gaya antar kartu tidak konsisten (campuran text-xs, border, warna bg, komponen manual vs komponen bawaan)

Perangkat pemakaian: campuran desktop + tablet. Semua logika bisnis (scan, filter, validasi overstock, submit) **tidak diubah** — ini murni perombakan presentasi.

## Bagian 1 — Layout & Responsive

**Desktop (≥ xl):**

- Grid 2 kolom proporsi **5/12 (kiri) – 7/12 (kanan)** menggantikan `xl:grid-cols-2` (1:1) — implementasi Tailwind: `grid grid-cols-1 gap-6 xl:grid-cols-12`, kolom kiri `xl:col-span-5`, kolom kanan `xl:col-span-7`.
- Kolom kiri (5/12): kartu **Detail Shopping** → kartu **Barang Dipilih** di bawahnya.
- Kolom kanan (7/12): kartu **Cari Produk** (tabel 5 kolom butuh ruang lebih).
- **Footer bar sticky** (`sticky bottom-0`, bg putih + border + shadow, z-index di atas konten):
  - Kiri: ringkasan `X jenis • Y qty` + peringatan overstock bila ada.
  - Kanan: tombol **Proses Shopping / Simpan** berukuran besar.
  - Menggantikan area submit bawah yang sekarang (div flex di akhir form).

**Tablet / HP (< xl):**

- Satu kolom berurutan: Detail Shopping → Barang Dipilih → Cari Produk.
- Footer sticky tetap di bawah — ringkasan & tombol Proses selalu terlihat.

## Bagian 2 — Tabel, Filter & Input Qty

**Ukuran & tipografi (semua tabel di Create/Edit):**

- Baris: `px-4 py-2.5`; nama produk `text-sm` (naik dari 12px); part number `font-mono text-xs` tetap.
- Header tabel seragam: `bg-gray-50 text-[11px] uppercase tracking-wider`, sticky saat tabel discroll.
- Input qty: `h-9 w-20 sm:w-24 text-sm text-center` — mempertahankan `onFocus` select-all (perbaikan sebelumnya).
- Angka Stok & Qty rata tengah dengan `tabular-nums`.

**Kartu Cari Produk (kanan):**

- Filter dua baris: baris 1 = pencarian full-width; baris 2 = Supplier + Model berdampingan + tombol `✕ Reset` (muncul hanya saat filter aktif). Label konsisten memakai komponen `Label`.
- Kolom tabel: Part# / Produk / Rak / Stok / Qty.
- Info bawah tetap: "Menampilkan X dari Y produk".

**Kartu Barang Dipilih (kiri, kompak):**

- Part# + nama digabung satu sel (part number kecil di atas nama) — kolom: Produk / Rak / Qty / ✕.
- Qty input sama dengan di Cari Produk; row overstock tetap highlight merah + label `⚠ Stok hanya X`.
- Empty state memakai komponen `EmptyState` bawaan (bukan div manual).

**Kartu Detail Shopping:**

- Form vertikal, jarak konsisten (`space-y-4`).
- Feedback hasil scan memakai komponen **Alert** bawaan: hijau (ok), kuning (stok habis), oranye (part tidak dikenal), lengkap dengan ikon.

## Bagian 3 — Konsistensi Index & Show

**Status badge di semua halaman (Index, Show):**

- Pakai komponen **Badge** bawaan (light variant): `draft` → `light`/abu, `shipped` → `info`/biru, `completed` → `success`/hijau.
- Label Bahasa Indonesia: **Draft**, **Dikirim**, **Selesai** (menggantikan teks Inggris mentah).

**Index:**

- Baris header menyatu: kiri = filter Cari + Status; kanan = tombol `Tambah Shopping`. Tombol tidak lagi terpisah di bawah filter.
- Tabel: baris `text-sm` seragam dengan Create/Edit; badge status memakai komponen Badge.

**Show:**

- Badge status memakai Badge + label Indonesia; tabel items disesuaikan spacing-nya (`px-4 py-2.5`, `text-sm`). Kartu info kiri tidak diubah.

## Non-Goals

- Tidak mengubah logika scan, filter, validasi overstock, maupun payload submit.
- Tidak menyentuh halaman lain di luar modul Shopping.
- Tidak mengubah backend / controller / API.

## File yang Disentuh

1. `resources/js/Pages/Transactions/Shopping/Create.tsx`
2. `resources/js/Pages/Transactions/Shopping/Edit.tsx`
3. `resources/js/Pages/Transactions/Shopping/Index.tsx`
4. `resources/js/Pages/Transactions/Shopping/Show.tsx`

Komponen bawaan yang dipakai: `Badge` (`ui/badge/Badge.tsx`), `Alert` (`ui/alert/`), `EmptyState` (`common/EmptyState.tsx`), `ComponentCard`, `Input` (dengan `selectOnFocus`), `SearchableSelect`, `Button`.

## Verifikasi

- `npm run build` sukses (vite).
- Cek visual manual via dev server: desktop + tablet (DevTools responsive), keseimbangan kolom, footer sticky, ukuran input qty, dan dark mode tetap terbaca.
- Perilaku yang tidak berubah: scan QR/barcode, auto-reopen scanner, filter, validasi overstock, konfirmasi submit.
