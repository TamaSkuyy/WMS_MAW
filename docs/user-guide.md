# Panduan Pengguna — Warehouse Management System

## Mitra Adhi Wasana

---

## Daftar Isi

1. [Login & Dashboard](#1-login--dashboard)
2. [Master Data](#2-master-data)
3. [Flow Penerimaan Barang (Receiving)](#3-flow-penerimaan-barang-receiving)
4. [Flow Pengeluaran Barang (Shopping)](#4-flow-pengeluaran-barang-shopping)
5. [Delivery Monitor](#5-delivery-monitor)
6. [Laporan](#6-laporan)
7. [TV Dashboard](#7-tv-dashboard)
8. [Setup & Administrasi](#8-setup--administrasi)

---

## 1. Login & Dashboard

### Login
1. Buka URL aplikasi di browser
2. Masukkan email & password yang diberikan admin
3. Klik **Sign In**

### Dashboard
Setelah login, Anda akan melihat **Dashboard** yang menampilkan ringkasan:

| Informasi | Keterangan |
|-----------|-----------|
| Total Produk | Jumlah produk aktif dalam sistem |
| Total Stok | Jumlah stok di semua rak |
| Stok Menipis | Produk yang stoknya rendah (butuh restock) |
| Cycle Pending | Cycle penerimaan yang belum selesai |

Dashboard juga menampilkan:
- **Delivery Monitor** — shortcut ke halaman monitor pengiriman
- **Stok Menipis** — daftar produk yang perlu di-restock
- **Cycle Menunggu** — daftar cycle draft yang belum diproses
- **Shopping Siap Kirim** — daftar pengiriman yang siap diproses

---

## 2. Master Data

Master Data adalah data dasar yang harus di-setup sebelum melakukan transaksi. Menu ini dapat diakses dari sidebar kiri.

### Supplier (Pemasok)
**Lokasi:** `Master Data > Suppliers`

1. Klik **Tambah Supplier**
2. Isi data:
   - **Kode / Singkatan** — singkatan unik (contoh: DWA, MMM). Kosongkan untuk dibuat otomatis
   - **Nama** — nama perusahaan supplier
   - **Kontak Person**, **Email**, **Telepon**
   - **Alamat** — jalan, kota, provinsi, kode pos, negara
3. Klik **Simpan**
4. Setelah tersimpan, buka **Edit Supplier** untuk mengatur:
   - **Jadwal Pengiriman (Slot Harian)** — centang slot waktu pengiriman (C1–C6)

### Produk
**Lokasi:** `Master Data > Products`

1. Klik **Tambah Produk** atau gunakan **Import** untuk upload banyak produk sekaligus
2. Isi data:
   - **Part Number** — kode unik produk
   - **Nama** — nama produk
   - **Model Kendaraan** — pilih model mobil terkait
   - **Supplier** — pilih pemasok
   - **Kategori** — pilih kategori produk
   - **Satuan** — unit (pcs, box, dll)
3. Klik **Simpan**

> 💡 **Tip:** Gunakan fitur **Import** untuk memasukkan ratusan produk sekaligus dari file Excel. Klik tombol **Download Template** untuk mendapat format yang benar.

### Model Kendaraan
**Lokasi:** `Master Data > Model Kendaraan`

- Tambah model kendaraan (contoh: Fortuner, Avanza, Yaris)
- Merek otomatis di-set ke **Toyota**

### Rak
**Lokasi:** `Master Data > Racks`

- Tambah rak penyimpanan dengan kode & zona (contoh: A-01, B-03)

### Kategori Produk
**Lokasi:** `Master Data > Kategori Produk`

- Tambah kategori untuk mengelompokkan produk (contoh: Body Parts, Electrical, Engine)

### Jadwal Slot Pengiriman
**Lokasi:** `Master Data > Jadwal Slot Pengiriman`

- 6 slot waktu harian (C1–C6) dengan jam yang bisa disesuaikan
- Default: C1 (07:30–09:30), C2 (09:30–11:30), C3 (11:30–13:30), C4 (13:30–15:30), C5 (15:30–17:30), C6 (17:30–19:30)

---

## 3. Flow Penerimaan Barang (Receiving)

Penerimaan barang dilakukan melalui **Cycle** — satu cycle mewakili satu kali penerimaan dari satu supplier.

### Cara 1: Quick Receive (Cepat)
**Lokasi:** `Transactions > Receiving > Quick Receive`

Cocok untuk penerimaan langsung tanpa perencanaan.

1. Pilih **Supplier**
2. Untuk setiap barang:
   - Pilih **Produk**
   - Pilih **Rak** tujuan
   - Masukkan **Quantity**
3. Klik **Simpan** — cycle otomatis selesai & stok bertambah

### Cara 2: Cycle Biasa (Terencana)
**Lokasi:** `Transactions > Receiving`

#### Membuat Cycle
1. Klik **Tambah Cycle**
2. Pilih **Supplier**
3. Tambahkan item yang akan diterima:
   - Pilih produk & masukkan quantity yang direncanakan
4. Klik **Simpan** — cycle tersimpan sebagai **Draft**
5. Nomor cycle otomatis berurutan per supplier (C1, C2, C3...)

#### Menerima Barang (Receive)
1. Buka cycle yang masih **Draft**
2. Klik **Receive**
3. Untuk setiap item:
   - Masukkan **Received Qty** (jumlah yang benar-benar diterima)
   - Pilih **Rak** penyimpanan
   - Tambahkan **Catatan** jika ada (misal: "2 pcs rusak")
4. Klik **Konfirmasi** — stok otomatis bertambah & cycle selesai

> ⚠️ Setelah di-receive, cycle tidak bisa diubah lagi. Stok sudah tercatat permanen.

### Flow Diagram
```
Supplier kirim barang
        │
        ▼
┌──────────────────┐
│  Buat Cycle      │  ← Pilih supplier, rencanakan item
│  (Status: Draft) │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  Terima Barang   │  ← Input jumlah aktual, pilih rak
│  (Status: Done)  │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  Stok Bertambah  │  ← Otomatis update di database
│  Selesai ✅       │
└──────────────────┘
```

---

## 4. Flow Pengeluaran Barang (Shopping)

Pengeluaran barang untuk dikirim ke pelanggan/pihak lain.

### Membuat Shopping
**Lokasi:** `Transactions > Shopping`

1. Klik **Tambah Shopping**
2. Isi data:
   - **Partner** — nama penerima/tujuan
   - **Items** — pilih produk & quantity yang akan dikirim
3. Klik **Simpan** — shopping tersimpan sebagai **Draft**

### Proses Pengiriman (Ship)
1. Buka shopping yang masih **Draft**
2. Klik **Ship**
3. Untuk setiap item:
   - Masukkan **Shipped Qty** (jumlah yang benar-benar dikirim)
4. Klik **Konfirmasi** — stok otomatis berkurang & shopping selesai

> ⚠️ Pastikan stok mencukupi sebelum melakukan ship. Sistem akan menolak jika stok tidak cukup.

### Flow Diagram
```
Pesanan keluar
       │
       ▼
┌──────────────────┐
│  Buat Shopping   │  ← Pilih partner & item
│  (Status: Draft) │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  Proses Kirim    │  ← Input jumlah aktual dikirim
│  (Status: Done)  │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  Stok Berkurang  │  ← Otomatis update di database
│  Selesai ✅       │
└──────────────────┘
```

---

## 5. Delivery Monitor

**Lokasi:** `Dashboard > klik "Delivery Monitor"` atau akses langsung ke `/delivery-monitor`

Halaman utama untuk memantau pengiriman supplier harian. Cocok ditampilkan di layar lebar (TV gudang).

### Fitur Utama

| Fitur | Fungsi |
|-------|--------|
| **Supplier Selector** | Pilih supplier yang ingin dipantau |
| **Tanggal** | Pilih tanggal monitoring (default: hari ini) |
| **TV Mode** | Tampilan full-screen untuk layar TV |
| **Rotate ON/OFF** | Auto-rotate antar supplier setiap beberapa detik |

### Status Supplier
Setiap supplier memiliki status yang dihitung otomatis:

| Status | Arti |
|--------|------|
| 🟢 **Done** | Semua slot hari ini sudah selesai diterima |
| 🟠 **Live** | Sedang dalam slot waktu, masih ada yang belum lengkap |
| 🔴 **Alert** | Slot sudah lewat tapi penerimaan belum lengkap |
| ⚪ **Standby** | Tidak ada jadwal pengiriman hari ini |

### Panel Detail Supplier
- **Progress Bar** — perbandingan rencana vs aktual per slot
- **Scheduled Parts** — daftar part yang dijadwalkan hari ini, auto-scroll
- **Ledger** — klik untuk melihat riwayat lengkap semua cycle supplier tersebut

### Parts Table
Tabel lengkap semua part yang terdaftar di sistem:
- **Search** — cari part number atau nama
- **Filter Kategori** — filter berdasarkan kategori produk
- **Status Filter** — All / Active Planned / Shortage / Matched / Over
- **Auto-scroll** — berjalan otomatis untuk tampilan TV

---

## 6. Laporan

### Receiving Report
**Lokasi:** `Report Transaction > Receiving Report`

Laporan penerimaan barang dengan filter:
- **Tanggal** — range tanggal
- **Supplier** — filter per supplier
- **Produk** — filter per produk

Fitur:
- **Export Excel** — unduh dalam format .xlsx
- **Export PDF** — unduh dalam format .pdf

### Shopping Report
**Lokasi:** `Report Transaction > Shopping Report`

Laporan pengeluaran barang dengan filter:
- **Tanggal** — range tanggal
- **Partner** — filter per penerima
- **Produk** — filter per produk

---

## 7. TV Dashboard

**Lokasi:** `/tv-dashboard`

Halaman khusus untuk ditampilkan di layar TV gudang:

- **Slide 1: Stok** — ringkasan total stok & stok menipis
- **Slide 2: Aktivitas Hari Ini** — jumlah receiving & shopping hari ini
- Auto-rotate antar slide

---

## 8. Setup & Administrasi

### Manajemen User
**Lokasi:** `Setup > Users`

- Tambah/edit/hapus user
- Import user dari Excel
- Set role & permission

### Manajemen Role & Permission
**Lokasi:** `Setup > Roles` & `Setup > Permissions`

- Atur hak akses setiap role
- Tentukan menu yang bisa diakses

### Menu Management
**Lokasi:** `Setup > Menu Management`

- Atur tampilan sidebar menu
- Tambah menu baru atau ubah urutan

### Master Data Lainnya
- **Jabatan**, **Lokasi Kerja**, **Departemen**, **Karyawan**, **Shift** — data pendukung untuk manajemen organisasi

---

## Ringkasan Alur Kerja Harian

```
┌─────────────────────────────────────────────────────────────┐
│                     MULAI HARI                               │
│  1. Buka Delivery Monitor → cek jadwal supplier hari ini     │
│  2. Supplier datang → buat Cycle / Quick Receive             │
│  3. Terima barang → input ke Cycle, stok otomatis update     │
│  4. Ada pesanan keluar → buat Shopping, kirim barang         │
│  5. Akhir hari → cek laporan Receiving & Shopping            │
└─────────────────────────────────────────────────────────────┘
```

---

> 📧 Untuk bantuan teknis, hubungi administrator sistem.
>
> 📅 Dokumen ini terakhir diperbarui: Juli 2026
