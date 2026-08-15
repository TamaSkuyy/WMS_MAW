# Desain UI/UX Mobile-Friendly Modul Shopping (Create/Edit)

- **Tanggal:** 2026-08-14
- **Status:** Disetujui user (3 bagian desain)
- **Cakupan:** `Shopping/Create.tsx` & `Shopping/Edit.tsx` (fokus), `Shopping/Index.tsx` (ringan). `Shopping/Show.tsx` tidak diubah.
- **Perangkat:** Keduanya, prioritas HP/handheld (≤ 480px)
- **Pola acuan:** redesign Receiving (`docs/superpowers/specs/2026-08-14-receiving-mobile-uiux-design.md`)

## Latar Belakang

Halaman Create/Edit Shopping saat ini sudah punya grid 5/7, footer sticky, dan tabel lega — namun di HP dua tabel (Cari Produk 5 kolom & Barang Dipilih 4 kolom) scroll horizontal, dan input qty belum konsisten dengan pola QtyStepper Receiving yang disukai user.

Tujuan: menghilangkan scroll horizontal di HP (kartu per item), konsisten dengan Receiving (stepper 44px), desktop tetap tabel. Logika bisnis (scan, filter, validasi, submit) **tidak diubah**.

## Bagian 1 — Layout & Responsive

**Urutan HP satu kolom (seperti sekarang): Detail Shopping → Barang Dipilih → Cari Produk; footer sticky tetap di bawah.**

- **Tabel Cari Produk** (kanan): dual rendering — ≥768px tabel 5 kolom (qty jadi QtyStepper); <768px **kartu per item**: part# (mono) + nama, badge stok & rak, QtyStepper, highlight biru (`bg-blue-50`) untuk item ber-qty > 0.
- **Tabel Barang Dipilih** (kiri): dual rendering — ≥768px tabel (Produk gabungan part#+nama, Rak, Qty stepper, ✕); <768px **kartu ringkas**: part# + nama + label overstock (bila ada), QtyStepper, tombol hapus ✕ (44px). Highlight merah overstock dipertahankan.
- **Filter Cari Produk**: Cari full-width; Supplier/Model menumpuk full-width di HP (`w-full sm:w-48/sm:w-56` sudah ada) — dipertahankan.
- **Footer sticky** (ringkasan `X jenis • Y qty`, badge overstock, tombol submit) dipertahankan apa adanya — sudah mobile-friendly.
- **Detail Shopping** (form + tombol scan + Alert feedback) dipertahankan — sudah cukup.

## Bagian 2 — QtyStepper & Perilaku

- Kedua tabel memakai komponen `QtyStepper` yang sudah ada (`resources/js/Components/QtyStepper.tsx`, 44px, select-on-focus) dengan **`max = stok` item** (termasuk stok relay/overflow dengan rack_id null).
- Konsekuensi disadari: input & tombol + ter-clamp ke stok → **overstock tidak bisa dibuat dari UI** — warning overstock, badge footer, dan tombol "Tidak Bisa Diproses" **tetap dipertahankan di kode** sebagai pengaman (mis. stok berubah setelah halaman dimuat), tidak dihapus.
- Item stok 0: tombol + ter-disable dari awal (konsisten dengan perilaku scan "Stok habis").
- **Scan part tetap +1 tanpa clamp** (logika lama tidak diubah). Jika hasil scan melewati stok, stepper menampilkan nilai > max (tombol + disabled, tombol − berfungsi; edit manual akan di-clamp ke stok).
- Tombol ✕ hapus item tetap di kedua mode.

## Bagian 3 — Index Ringan & Verifikasi

**Index:**
- Filter (Cari Lokasi & Status) full-width di HP: `w-full sm:min-w-[200px]` / `w-full sm:min-w-[160px]` (pola Cycles Index).
- Tombol aksi (Lihat/Edit/Hapus) diperbesar ke 44px (pola Cycles Index: Link custom + w-11 h-11 + ikon w-5 h-5).

**Show:** tidak diubah (sudah cukup dari redesign sebelumnya).

**File:**
- Ubah: `resources/js/Pages/Transactions/Shopping/Create.tsx`, `.../Shopping/Edit.tsx` (fokus), `.../Shopping/Index.tsx` (ringan)
- Pakai ulang: `resources/js/Components/QtyStepper.tsx` (tanpa perubahan)

**Verifikasi (tanpa test frontend, sesuai keputusan project):**
- `npm run build` sukses.
- Cek visual: HP 390px (kartu item di kedua tabel, stepper, footer sticky), tablet 768px (kembali ke tabel), desktop 1280px, dark mode.
- Perilaku lama terjaga: scan +1, filter + Reset, submit (konfirmasi tunggal), footer sticky, Alert feedback scan, empty state.
- Implementasi memakai pola dari skill `modern-web-guidance` (sudah diizinkan user).

## Non-Goals

- Tidak mengubah backend / controller / logika store/update/ship.
- Tidak mengubah `Shopping/Show.tsx`.
- Tidak menghapus logika peringatan overstock.
