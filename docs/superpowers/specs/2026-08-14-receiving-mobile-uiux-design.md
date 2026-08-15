# Desain UI/UX Mobile-Friendly Modul Receiving (Cycles)

- **Tanggal:** 2026-08-14
- **Status:** Disetujui user (3 bagian desain)
- **Cakupan:** `Cycles/Show.tsx`, `Cycles/QuickReceive.tsx` (fokus), `Cycles/Index.tsx` (sentuhan ringan)
- **Pendekatan terpilih:** Kartu item di HP + tabel di desktop, dengan QtyStepper dan sticky bottom bar
- **Perangkat:** Keduanya, prioritas HP/handheld (≤ 480px)

## Latar Belakang

Staff penerimaan di lapangan paling banyak memakai HP/handheld. Kondisi sekarang:

- Show (Terima Barang): tabel `min-w-[850px]` → di HP harus geser horizontal; input qty kecil (w-14 sm:w-20); rak pakai SearchableSelect sempit; tombol submit di bawah tabel (harus scroll).
- QuickReceive (Terima Cepat): tabel 5 kolom sempit; input qty kecil; setup di kiri atas.
- Index: tabel 6 kolom scroll horizontal; tombol aksi kecil.

Tujuan: mobile-first, target sentuh min 44px, aman dipakai sarung tangan, desktop tetap nyaman. Logika bisnis (scan +1, validasi, submit) **tidak diubah**.

## Bagian 1 — Layout & Responsive

**Show — form Terima Barang:**
- Dual rendering daftar item:
  - ≥ 768px (`hidden md:block`): tabel seperti sekarang, tapi sel qty memakai QtyStepper dan rak select lebih lebar (`w-40 sm:w-48`).
  - < 768px (`md:hidden`): tiap item jadi **kartu** — baris 1: part# (mono) + nama + badge qty doc; baris 2: QtyStepper; baris 3: rak (SearchableSelect full-width) + label sumber rak (Default/Terakhir/⚠ Relay); baris 4: input catatan (full-width).
- Highlight item tanpa rak tetap (latar merah muda) di kedua mode.
- Kartu Info Cycle tetap di atas (grid sudah menumpuk natural di HP).
- **Sticky bottom bar** (di dalam form, `sticky bottom-0 z-10`): kiri = progres `X/Y selesai · total qty`; kanan = tombol ikon **📷 Scan** (membuka QrScanner) + tombol **Selesaikan Penerimaan**. Tetap terlihat saat scroll di semua ukuran layar.

**QuickReceive:**
- Setup (supplier + tombol scan) tetap di atas, full-width di HP.
- Daftar item dual rendering sama: tabel di desktop, kartu di HP (part#, nama, QtyStepper min 1, rak full-width, tombol hapus).
- Highlight item tanpa rak dipertahankan.
- **Sticky bottom bar**: kiri = `Jenis X · Total qty Y`; kanan = **Reset** + **Selesaikan Penerimaan**.

## Bagian 2 — QtyStepper & Interaksi

**Komponen baru `resources/js/Components/QtyStepper.tsx`:**
- Props: `value: number`, `onChange: (n: number) => void`, `min?: number` (default 0), `max?: number`.
- Tombol − / + : `h-11 w-11` (44px), teks besar, disabled di min/max.
- Input tengah: `h-11 w-16 text-center text-base tabular-nums`, `inputMode="numeric"`, `onFocus` select-all.
- Layout: `flex items-center gap-1`.

**Perilaku:**
- Show: stepper mengubah `received_quantity` (min 0, max = `quantity` doc — tombol + berhenti di qty doc; angka di atas doc tetap bisa diketik manual).
- QuickReceive: stepper mengubah `quantity` (min 1).
- Scan QR tetap menambah +1 (perilaku lama).
- Progres `X/Y selesai` = jumlah item dengan `received_quantity >= quantity`.

## Bagian 3 — Konsistensi & Verifikasi

**Index Cycles (sentuhan ringan):**
- Filter Supplier/Status full-width di HP (`w-full sm:w-auto`), label pakai komponen `Label`.
- Tombol aksi (view/edit/delete) diperbesar ke min 44px untuk sentuhan; tabel tetap scroll horizontal di HP.

**Create Cycles:** tidak diubah.

**Dark mode:** semua elemen baru (kartu item, stepper, sticky bar) menyertakan kelas `dark:`.

**File:**
- Baru: `resources/js/Components/QtyStepper.tsx`
- Ubah: `resources/js/Pages/Transactions/Cycles/Show.tsx`, `.../Cycles/QuickReceive.tsx`, `.../Cycles/Index.tsx` (ringan)

**Verifikasi (tanpa test frontend, sesuai keputusan project):**
- `npm run build` sukses.
- Cek visual: HP 390px (kartu item, sticky bar, stepper 44px), tablet 768px, desktop 1280px, dark mode.
- Perilaku lama terjaga: scan +1 (feedback Alert tetap), validasi rack, submit receive/quick-receive, Alert error & flash (dari perbaikan sebelumnya) tetap tampil.
- Implementasi memakai pola dari skill `modern-web-guidance` (sudah diizinkan user).

## Non-Goals

- Tidak mengubah backend / controller / logika receive.
- Tidak mengubah halaman lain di luar yang disebutkan.
- Tidak menyentuh alur scan (QrScanner) selain penempatan tombol.
