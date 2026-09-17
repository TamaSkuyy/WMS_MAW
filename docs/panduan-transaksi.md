# Panduan Transaksi — Warehouse Management System

## Mitra Adhi Wasana

---

## Daftar Isi

1. [Penerimaan Barang (Receiving)](#1-penerimaan-barang-receiving)
   - [A. Quick Receive — Terima Langsung](#a-quick-receive--terima-langsung)
   - [B. Cycle — Terima Terencana](#b-cycle--terima-terencana)
2. [Pengeluaran Barang (Shopping)](#2-pengeluaran-barang-shopping)
   - [A0. Alur Import 2 Langkah (header dulu, barang kemudian)](#a0-alur-import-2-langkah-header-dulu-barang-kemudian)
   - [A. Membuat Shopping Baru](#a-membuat-shopping-baru)
   - [B. Proses Pengiriman (Ship)](#b-proses-pengiriman-ship)
3. [Melihat & Mencari Stok](#3-melihat--mencari-stok)
4. [Memantau Pengiriman (Delivery Monitor)](#4-memantau-pengiriman-delivery-monitor)
5. [Tips & Troubleshooting](#5-tips--troubleshooting)

---

## 1. Penerimaan Barang (Receiving)

Ada dua cara menerima barang: **Quick Receive** (langsung) dan **Cycle** (terencana).

### A. Quick Receive — Terima Langsung

Gunakan jika barang datang dan ingin langsung dicatat tanpa perencanaan.

#### Langkah-langkah:

```
1. Buka menu: Transactions > Receiving
2. Klik tombol "Quick Receive"
3. Pilih Supplier dari dropdown
4. Tambahkan item barang:
   ┌──────────────────────────────────────────────────────┐
   │ Produk:    [Pilih dari dropdown atau scan QR]        │
   │ Rak:       [Pilih rak tempat menyimpan]              │
   │ Quantity:  [Jumlah yang diterima]                    │
   │ [+ Tambah Barang]                                    │
   └──────────────────────────────────────────────────────┘
5. Klik "Simpan"
```

#### Hasil:
- ✅ Cycle otomatis dibuat & langsung selesai
- ✅ Stok produk bertambah di rak yang dipilih
- ✅ Tercatat di Delivery Monitor (jika dalam jam slot)

#### Kapan menggunakan:
- Barang datang mendadak tanpa jadwal
- Jumlah sedikit (1-3 item)
- Operator gudang melakukan scan langsung

---

### B. Cycle — Terima Terencana

Gunakan jika ingin merencanakan penerimaan terlebih dahulu, atau ada jadwal supplier.

#### B1. Membuat Cycle Baru

```
1. Buka menu: Transactions > Receiving
2. Klik "Tambah Cycle"
3. Isi form:
   ┌─────────────────────────────────────────────┐
   │ Supplier:  [Pilih supplier]                 │
   │                                              │
   │ Items:                                       │
   │ ┌──────────────┬──────────┬────────────────┐ │
   │ │ Produk       │ Qty Plan │                │ │
   │ ├──────────────┼──────────┼────────────────┤ │
   │ │ Filter Oil   │ 100      │ [✕ hapus]     │ │
   │ │ Brake Pad    │ 50       │ [✕ hapus]     │ │
   │ └──────────────┴──────────┴────────────────┘ │
   │ [+ Tambah Item]                              │
   │                                              │
   │ Notes: [Catatan tambahan (opsional)]         │
   └─────────────────────────────────────────────┘
4. Klik "Simpan"
```

> 💡 **Catatan:**
> - Nomor cycle otomatis (C1, C2, C3...) per supplier
> - Cycle disimpan sebagai **Draft** — belum mempengaruhi stok
> - Tanggal & slot waktu otomatis terisi sesuai jam saat ini

#### B1a. Membuat Cycle dari File "Data Order" (Import Massal)

Untuk supplier dengan file rencana harian (mis. lampiran email berisi kolom
`Part Number`, `Supplier`, dan `CYCLE 1..16` — contoh sheet "EMAIL"):

```
1. Buka menu: Transactions > Cycle > tombol "📄 Import Data Order"
2. Pilih Tanggal Pengiriman (file biasanya tidak memuat tanggal)
3. Pilih file (.xlsx / .xls / .csv), klik "Tinjau Hasil"
4. Cek preview:
   - Ringkasan: jumlah supplier, gelombang/cycle, item, dan total pcs
   - Per supplier terlihat gelombang mana yang akan dibuat (CYCLE 1, 2, ...)
   - Part/supplier yang tidak dikenal akan dilaporkan & dilewati
5. Klik "Buat N Cycle Draft"
```

Hasilnya: satu cycle **draft** per (supplier × gelombang berisi qty). Nomor
cycle = nomor gelombang **hari itu** per supplier (mulai 1 tiap tanggal,
unik per supplier + tanggal) — mengikuti `CYCLE 1..16` di file. Tanggal lain
boleh mulai dari 1 lagi. Selanjutnya terima seperti biasa lewat alur
Cycle/Receive.

> **File sering di-update supplier (data lama tetap ada) — sudah diantisipasi:**
> - Preview mendeteksi apakah tanggal tsb sudah pernah di-import (ada cycle
>   draft/aktif hasil import Data Order).
> - Isi file **sama persis** → import dilewati otomatis, tidak bikin duplikat.
> - Isi file **berubah** → pilih **"Ganti"** (cycle DRAFT lama tanggal tsb
>   dihapus lalu dibuat ulang versi terbaru; cycle yang sudah diterima
>   `receiving/completed` tidak pernah dihapus) atau **"Tambah"** (buat di
>   samping yang lama, nomor lanjut).
> - Part lama yang qty-nya 0 di file baru otomatis dilewati (tidak ikut dibuat).

#### B2. Menerima Barang (Receive)

Saat barang benar-benar datang:

```
1. Buka cycle yang masih "Draft"
   (dari daftar: Transactions > Receiving)
2. Klik tombol "Receive"
3. Isi data penerimaan untuk setiap item:
   ┌──────────────────────────────────────────────────────┐
   │ Produk: Filter Oil (Rencana: 100 pcs)                │
   │                                                       │
   │ Diterima:   [98]  pcs  ← jumlah aktual               │
   │ Rak:        [A-01]       ← pilih rak penyimpanan      │
   │ Catatan:    [2 pcs rusak] ← alasan selisih (opsional) │
   └──────────────────────────────────────────────────────┘
4. Ulangi untuk setiap item
5. Klik "Konfirmasi Penerimaan"
```

#### Hasil:
- ✅ Status cycle berubah jadi **Completed**
- ✅ Stok bertambah sesuai jumlah yang diterima
- ✅ Tercatat tanggal & jam penerimaan
- ✅ Muncul di Delivery Monitor

#### Keadaan Khusus:

| Situasi | Tindakan |
|---------|----------|
| Barang datang kurang | Isi `Diterima` < `Rencana` → status "Shortage" |
| Barang datang lebih | Isi `Diterima` > `Rencana` → status "Over" |
| Barang diterima bertahap | Bisa receive berkali-kali sebelum status completed |
| Salah input receive | ❌ Tidak bisa di-undo — hubungi admin |

---

### Perbandingan: Quick Receive vs Cycle

| | Quick Receive | Cycle |
|-----|--------------|-------|
| Perencanaan | Tidak perlu | Perlu (draft dulu) |
| Jumlah item | Sedikit | Banyak |
| Riwayat | Tercatat | Tercatat + ada draft history |
| Slot waktu | Otomatis | Otomatis |
| Cocok untuk | Receiving spontan | Receiving terjadwal |

---

## 2. Pengeluaran Barang (Shopping)

Shopping digunakan untuk mencatat barang yang keluar dari gudang.

### A0. Alur Import 2 Langkah (header dulu, barang kemudian)

Alur ini dipakai kalau data dari pusat/TAM datang bertahap: **line + frame number
dulu**, lalu **daftar barang + qty**. Dua langkah ini dipisah supaya frame yang
dikirim bisa dicek lebih dulu sebelum barangnya diisi.

**Langkah 1 — Input Header (line + frame number)**

```
1. Buka menu: Transactions > Shopping
2. Klik tombol "1. Input Header"
3. Isi tabel:
   ┌────┬──────────────────────┬─────────────────────┬─────────┐
   │ #  │ Line / Lokasi Tujuan │ Frame Number        │ Cripple │
   ├────┼──────────────────────┼─────────────────────┼─────────┤
   │ 1  │ LINE A               │ FR-00123            │  [ ]    │
   │ 2  │ LINE A               │ FR-00124            │  [ ]    │
   └────┴──────────────────────┴─────────────────────┴─────────┘
   • "+ 1 Baris" / "+ 5 Baris" untuk menambah baris
   • Tempel (Ctrl+V) beberapa frame sekaligus: satu frame per baris
   • Tekan Enter di baris terakhir untuk menambah baris baru
4. Klik "Simpan Header"
```

Hasil: frame tersimpan sebagai **Draft tanpa barang**. Frame yang nomornya sudah
ada akan dilewati dan dilaporkan (tidak dobel).

**Langkah 2 — Import Barang (part number + qty)**

```
1. Klik tombol "2. Import Barang" (atau "Lanjut: Import Barang" dari langkah 1)
2. Pilih file Excel/CSV dengan kolom:
   ┌──────────────┬─────────────┬──────────┐
   │ Frame Number │ Part Number │ Quantity │
   ├──────────────┼─────────────┼──────────┤
   │ FR-00123     │ P-5188      │ 10       │
   │ FR-00124     │ P-5190      │ 4        │
   └──────────────┴─────────────┴──────────┘
3. Cocokkan kolom (biasanya sudah otomatis) → "Start Import"
4. Tunggu sampai muncul "Import selesai"
```

- Barang masuk ke frame yang **sudah terdaftar** hasil Langkah 1.
- Frame yang belum terdaftar → baris ditolak dengan pesan
  `Frame "..." belum terdaftar di WMS`. Centang **"Buat frame otomatis"** di modal
  import kalau ingin sistem membuat frame baru sendiri (dipakai kalau alur dari
  pusat/TAM berubah mendadak).
- Bagian **Confirmed**, **Cripple**, **Modify Date** opsional.

> ℹ️ Alur **Import Gabungan** (satu file berisi frame + barang + qty sekaligus)
> dan input manual **"Tambah Shopping"** tetap tersedia. Kalau pusat/TAM mengubah
> lagi urutan kerjanya, tidak ada alur yang perlu dihapus.

### A. Membuat Shopping Baru

> ℹ️ Di form Tambah/Edit Shopping ada combobox **Model Kendaraan (opsional)** dan
> **Suffix (opsional)** — bisa dicari, pilihannya dari **Master Data > Model Kendaraan**
> plus nilai yang pernah diinput (jadi tidak perlu hafal). Boleh dikosongkan; nilai baru
> juga boleh diketik.
> Isian itu menyaring daftar produk (mis. ketik `fortuner` → hanya part Fortuner yang
> tampil) **dan** tersimpan sebagai catatan transaksi (tampil di Detail Shopping).

```
1. Buka menu: Transactions > Shopping
2. Klik "Tambah Shopping"
3. Isi form:
   ┌─────────────────────────────────────────────┐
   │ Partner:  [Nama penerima / tujuan]          │
   │                                              │
   │ Items:                                       │
   │ ┌──────────────┬──────┬────────────────────┐ │
   │ │ Produk       │ Qty  │                    │ │
   │ ├──────────────┼──────┼────────────────────┤ │
   │ │ Filter Oil   │ 20   │ [✕ hapus]         │ │
   │ └──────────────┴──────┴────────────────────┘ │
   │ [+ Tambah Item]                              │
   └─────────────────────────────────────────────┘
4. Klik "Simpan" → status: Draft
```

### B. Proses Pengiriman (Ship)

Saat barang siap dikirim:

```
1. Buka shopping yang masih "Draft"
2. Klik tombol "Ship"
3. Verifikasi jumlah yang dikirim:
   ┌──────────────────────────────────────────────┐
   │ Produk: Filter Oil (Rencana: 20 pcs)         │
   │                                               │
   │ Dikirim:  [20]  pcs  ← jumlah aktual dikirim │
   └──────────────────────────────────────────────┘
4. Klik "Konfirmasi Pengiriman"
```

#### Hasil:
- ✅ Status shopping berubah jadi **Completed**
- ✅ Stok berkurang sesuai jumlah yang dikirim
- ✅ Tercatat tanggal pengiriman

> ⚠️ **Perhatian:** Pastikan stok mencukupi. Sistem akan menolak jika jumlah dikirim melebihi stok tersedia.

---

## 3. Melihat & Mencari Stok

**Lokasi:** `Transactions > Stocks`

Halaman ini menampilkan semua stok di gudang:

```
┌──────────────────────────────────────────────────────┐
│ Cari: [_____________]  🔍                            │
│                                                       │
│ ┌──────────┬──────────────┬────────┬──────────────┐ │
│ │ Part No  │ Nama Produk  │ Rak    │ Qty          │ │
│ ├──────────┼──────────────┼────────┼──────────────┤ │
│ │ P5188-.. │ Grade Emblem │ A-01   │ 200          │ │
│ │ P5162-.. │ Side Visor   │ B-03   │ 45           │ │
│ │ P5401-.. │ Black Mirror │ A-02   │ 3 ⚠️        │ │
│ └──────────┴──────────────┴────────┴──────────────┘ │
└──────────────────────────────────────────────────────┘
```

- **Search** — cari berdasarkan nama produk atau part number
- **Info Rak** — lihat di rak mana barang disimpan
- **Stok Rendah** — quantity kecil perlu restock

### Baris RELAY (tanpa rak)

Barang yang **sudah diterima tapi belum ditempatkan di rak** disimpan sebagai
stok **RELAY** (kolom rak kosong) dan ditandai `⚠ RELAY` dengan latar kuning.
Ini normal selama barang memang belum masuk rak.

Yang **tidak normal**: satu part muncul sebagai **dua baris** — satu di rak, satu
di RELAY dengan qty yang mirip. Itu tanda stok ganda, biasanya berasal dari
**Stock Opname yang file-nya tidak memuat kolom `RAK`** (semua baris dianggap
RELAY, sehingga dibuat baris RELAY baru padahal stok sudah ada di rak).

Dalam pratinjau opname, baris semacam itu sekarang diberi peringatan:

```
Ada di fisik tapi belum ada baris stok — akan dibuat di RELAY ⚠ produk ini SUDAH
punya stok di A1:50 — kalau barangnya sama, JANGAN diterapkan; isi kolom RAK
dengan rak yang benar supaya stok tidak dobel.
```

Kalau barangnya sama: isi kolom `RAK` di file (atau hapus barisnya) lalu upload
ulang. Perbaikan data yang telanjur dobel dilakukan admin lewat
`php artisan stocks:audit` (lihat `docs/production-server-setup.md` §9.7).

---

## 4. Memantau Pengiriman (Delivery Monitor)

**Lokasi:** Klik "Delivery Monitor" di Dashboard, atau akses `/delivery-monitor`

### Tampilan Utama

```
┌─────────────────────────────────────────────────────────────┐
│ [LOGO] MITRA ADHI WASANA                     📅 24 Jul 2026 │
│ Warehouse Part Delivery Monitor                             │
│                                                              │
│ Supplier: [DWA ▼]  │  TV Mode [ON]  Rotate [ON]  OTIF: 85% │
└─────────────────────────────────────────────────────────────┘
```

### Cara Membaca Status

| Warna | Status | Arti |
|-------|--------|------|
| 🟢 Hijau | **Done** | Semua slot selesai, barang sudah diterima |
| 🟠 Oranye | **Live** | Sedang dalam jam slot, menunggu penerimaan |
| 🔴 Merah | **Alert** | Slot sudah lewat tapi belum lengkap — perlu tindakan |
| ⚪ Abu-abu | **Standby** | Supplier ini tidak ada jadwal hari ini |

### Fitur

1. **Pilih Supplier** — dropdown di header untuk ganti supplier
2. **Ganti Tanggal** — date picker untuk lihat hari sebelumnya
3. **TV Mode** — tampilan penuh untuk layar TV (sidebar hilang)
4. **Rotate** — otomatis ganti supplier setiap beberapa detik

### Memahami Panel Supplier (sebelah kiri)

```
┌──────────────────────────────┐
│ DWA — PT. Dwi Warna Abadi   │
│ 🟠 Live                      │
│                               │
│ ████████░░░░░░ 60%           │
│ C1 07:30-09:30               │
│ ██████████████ 100% ✅        │
│ C2 09:30-11:30               │
│ ░░░░░░░░░░░░░░ 0% ⚠️        │
│ C3 11:30-13:30               │
│                               │
│ 📋 Today's Scheduled Parts:  │
│ ┌────────────────────────┐   │
│ │ C1 Grade Emblem (VRZ)  │   │
│ │    Plan: 100 / Recv: 80│   │
│ │ C1 Side Visor Innova   │   │
│ │    Plan: 50 / Recv: 50 │   │
│ │ ... (auto-scroll)      │   │
│ └────────────────────────┘   │
│                               │
│ [📒 Buka Ledger]             │
└──────────────────────────────┘
```

- **Progress Bar** — hijau = selesai, merah = belum
- **Scheduled Parts** — auto-scroll untuk daftar panjang
- **Ledger** — klik untuk lihat riwayat lengkap supplier

### Memahami Tabel Part (sebelah kanan)

Tabel menampilkan semua part dengan status penerimaan per slot:

```
┌────┬──────────┬──────────┬──────────┬────┬───┬───┬───┬──────┐
│ No │ Part No  │ Supplier │ PartName │Cat │C1 │C2 │C3 │Status│
├────┼──────────┼──────────┼──────────┼────┼───┼───┼───┼──────┤
│ 1  │ P5188-.. │ DWA      │ Grade Em │Elec│100│ — │ — │ ✔ OK │
│    │          │          │          │    │ 80│   │   │      │
│ 2  │ P5162-.. │ DWA      │ Side Vis │Body│ — │ 50│ — │ ✔ OK │
│    │          │          │          │    │   │ 50│   │      │
│ 3  │ P5401-.. │ DWA      │ Mirror   │Acc │ — │ — │ 20│ ⚠ -15│
│    │          │          │          │    │   │   │  5│Short │
└────┴──────────┴──────────┴──────────┴────┴───┴───┴───┴──────┘
```

Cara membaca:
- **C1, C2, ..., C6** — slot pengiriman. `100/80` artinya rencana 100, diterima 80
- **Tanda `—`** — part tidak dijadwalkan di slot tersebut
- **✔ OK** — semua slot lengkap
- **⚠ -15 Short** — kurang 15 pcs

### Filter & Search

Gunakan search bar dan filter untuk menemukan part spesifik:

```
[Cari Part Number atau Nama...]  [Kategori ▼]  [Status ▼]
```

- **All Items** — semua part
- **Active Planned Only** — hanya part yang ada jadwal
- **Shortage/Delayed ⚠️** — part yang kurang
- **Matched OK ✅** — part yang sudah pas
- **Over-Deliveries 📦** — part yang kelebihan

---

## 5. Tips & Troubleshooting

### Tips Efisiensi

| Tips | Keterangan |
|------|-----------|
| Gunakan Quick Receive | Untuk penerimaan harian tanpa rencana |
| Gunakan Cycle | Untuk supplier dengan jadwal tetap (C1-C6) |
| Cek Delivery Monitor pagi hari | Lihat supplier mana yang dijadwalkan hari ini |
| Filter "Shortage" di Parts Table | Cepat temukan part yang belum lengkap |
| TV Mode + Rotate ON | Pasang di layar gudang untuk monitoring real-time |
| Export laporan | Akhir minggu/bulan, export Excel untuk arsip |

### Masalah Umum

| Masalah | Solusi |
|---------|--------|
| Stok tidak bertambah setelah receive | Refresh halaman, cek halaman Stocks |
| Cycle tidak muncul di Delivery Monitor | Pastikan supplier punya jadwal slot (Edit Supplier) |
| Tidak bisa receive cycle | Cycle harus status "Draft" atau "Receiving" |
| Nama supplier tidak muncul | Tambah supplier dulu di Master Data > Suppliers |
| Produk tidak ada di dropdown | Tambah produk dulu di Master Data > Products |
| Salah input jumlah receive | ❌ Tidak bisa di-undo sendiri — hubungi admin |

### Alur Cepat Harian

```
PAGI (07:30)
  └─ Buka Delivery Monitor → cek supplier hari ini

SIANG (saat supplier datang)
  └─ Quick Receive atau Cycle Receive → input barang masuk

SORE (menjelang tutup)
  └─ Cek Delivery Monitor → pastikan semua "Done"
  └─ Proses Shopping jika ada pengiriman keluar
  └─ Cek Stok → catat yang perlu di-restock
```

---

> 📧 Ada pertanyaan atau butuh bantuan? Hubungi administrator sistem.
>
> 📅 Dokumen ini terakhir diperbarui: Juli 2026
