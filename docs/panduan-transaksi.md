# Panduan Transaksi — Warehouse Management System

## Mitra Adhi Wasana

---

## Daftar Isi

1. [Penerimaan Barang (Receiving)](#1-penerimaan-barang-receiving)
   - [A. Quick Receive — Terima Langsung](#a-quick-receive--terima-langsung)
   - [B. Cycle — Terima Terencana](#b-cycle--terima-terencana)
2. [Pengeluaran Barang (Shopping)](#2-pengeluaran-barang-shopping)
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

### A. Membuat Shopping Baru

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
