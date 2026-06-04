# Dokumentasi Database — WMS MAW

> Warehouse Management System untuk gudang suku cadang otomotif  
> Versi 1.0 — 4 Juni 2026

---

## Daftar Isi

1. [Gambaran Umum](#gambaran-umum)
2. [Alur Bisnis](#alur-bisnis)
3. [Struktur Tabel](#struktur-tabel)
   - [Master Data](#1-master-data)
   - [Inbound — Barang Masuk](#2-inbound--barang-masuk)
   - [Inventory — Stok Gudang](#3-inventory--stok-gudang)
   - [Outbound — Barang Keluar](#4-outbound--barang-keluar)
   - [System](#5-system)
4. [Relasi Antar Tabel](#relasi-antar-tabel)
5. [Status Lifecycle](#status-lifecycle)
6. [Aturan Penghapusan Data](#aturan-penghapusan-data)

---

## Gambaran Umum

Sistem ini mengelola **gudang suku cadang otomotif** secara end-to-end: dari barang masuk (supplier), penyimpanan (rak), sampai barang keluar (mitra). Total **13 tabel** yang terbagi dalam 5 kelompok.

### Ringkasan Tabel

| # | Tabel | Kategori | Deskripsi Singkat |
|---|-------|----------|-------------------|
| 1 | `suppliers` | Master | Data pemasok / supplier |
| 2 | `supplier_addresses` | Master | Alamat supplier (utama, pengiriman, billing) |
| 3 | `vehicle_models` | Master | Model kendaraan (contoh: Fortuner VRZ - Toyota) |
| 4 | `product_categories` | Master | Kategori part (Body Parts, Engine, Electrical, dll) |
| 5 | `products` | Master | Master part/suku cadang (Part Number, Nama, Harga) |
| 6 | `racks` | Master | Lokasi rak penyimpanan di gudang |
| 7 | `cycles` | Inbound | Dokumen penerimaan barang per supplier per cycle |
| 8 | `cycle_items` | Inbound | Detail part yang diterima dalam satu cycle |
| 9 | `stocks` | Inventory | Stok real-time per produk per rak |
| 10 | `shipments` | Outbound | Dokumen pengiriman barang ke mitra |
| 11 | `shipment_items` | Outbound | Detail part yang dikirim dalam satu shipment |
| 12 | `users` | System | User / pengguna sistem |
| 13 | `menus` | System | Navigasi sidebar (dinamis) |
| 14 | `import_logs` | System | Log import data dari Excel |

---

## Alur Bisnis

```
INBOUND                          STORAGE                     OUTBOUND
=======                          =======                     ========

Supplier kirim barang            Stok tersimpan              Buat shipment ke mitra
       │                         per produk                  (draft)
       ▼                         per rak                          │
  Cycle dibuat (draft)                │                     Pilih produk & rak
       │                              │                          │
       ▼                              │                          ▼
  Petugas verifikasi                  │                   Proses "Ship"
  & input qty aktual                  │                   → validasi stok cukup
  (receiving)                         │                   → stok otomatis
       │                              │                     berkurang
       ▼                              │                          │
  Barang disimpan ke rak              │                          ▼
  → Stok bertambah (++)              │                    Status shipped
       │                              │                          │
       ▼                              ▼                          ▼
  Status: completed             Cek real-time              Mitra terima
                                per rak/produk             Status: completed
```

### Penjelasan Alur

**1. Barang Masuk (Inbound)**
Supplier mengirim barang. Staff membuat dokumen **Cycle** yang berisi daftar part dan jumlah yang dikirim. Saat barang tiba, staff memverifikasi dan mencatat jumlah aktual yang diterima (`received_quantity` bisa berbeda dari `quantity` di dokumen — misalnya ada yang rusak). Barang lalu disimpan ke **Rak** tertentu, dan **Stok** otomatis bertambah.

**2. Penyimpanan (Inventory)**
Stok dilacak per produk per rak. Satu produk bisa disimpan di beberapa rak berbeda. Stok selalu real-time: bertambah dari Cycle (inbound) dan berkurang dari Shipment (outbound).

**3. Barang Keluar (Outbound)**
Staff membuat dokumen **Shipment** untuk mengirim barang ke mitra. Sistem memvalidasi stok cukup sebelum memproses. Saat shipment diproses, stok otomatis berkurang.

---

## Struktur Tabel

### 1. Master Data

#### `suppliers` — Pemasok

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `name` | VARCHAR(255) UNIQUE | Nama perusahaan supplier |
| `contact_person` | VARCHAR(255) | Nama kontak person |
| `email` | VARCHAR(255) UNIQUE | Email |
| `phone` | VARCHAR(20) | Nomor telepon |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

#### `supplier_addresses` — Alamat Supplier

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `supplier_id` | BIGINT (FK) | ID supplier |
| `street` | VARCHAR(255) | Alamat jalan |
| `city` | VARCHAR(100) | Kota |
| `state` | VARCHAR(100) | Provinsi |
| `postal_code` | VARCHAR(20) | Kode pos |
| `country` | VARCHAR(100) | Negara (default: Indonesia) |
| `address_type` | ENUM | `primary` / `shipping` / `billing` |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Aturan:** Satu supplier hanya boleh punya 1 alamat per tipe (`supplier_id` + `address_type` = UNIQUE).  
> **Hapus:** Kalau supplier dihapus → alamat ikut terhapus (CASCADE).

#### `vehicle_models` — Model Kendaraan

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `name` | VARCHAR(100) | Nama model (contoh: "Fortuner VRZ") |
| `brand` | VARCHAR(100) | Merek (contoh: "Toyota") |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Aturan:** Kombinasi `name` + `brand` harus unik.

#### `product_categories` — Kategori Produk

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `name` | VARCHAR(100) UNIQUE | Nama kategori (contoh: "Body Parts") |
| `description` | TEXT | Deskripsi opsional |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

#### `products` — Master Produk / Part

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `part_number` | VARCHAR(50) UNIQUE | Kode part / SKU (contoh: "P5188-0KA03") |
| `name` | VARCHAR(255) | Nama part (contoh: "Grade Emblem VRZ") |
| `vehicle_model_id` | BIGINT (FK) | Model kendaraan terkait |
| `supplier_id` | BIGINT (FK) | Supplier part ini |
| `category_id` | BIGINT (FK) | Kategori part |
| `unit` | VARCHAR(20) | Satuan: `pcs`, `set`, `box`, `unit` (default: `pcs`) |
| `description` | TEXT | Keterangan tambahan |
| `base_price` | DECIMAL(12,2) | Harga beli standar (Rupiah) |
| `is_active` | BOOLEAN | Status aktif/nonaktif (default: `true`) |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Hapus:** Produk tidak bisa dihapus jika masih direferensi oleh Cycle, Stock, atau Shipment (RESTRICT). Gunakan `is_active = false` untuk menonaktifkan.

#### `racks` — Rak Penyimpanan

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `code` | VARCHAR(20) UNIQUE | Kode rak (contoh: "A-01", "B-03") |
| `zone` | VARCHAR(50) | Zona (contoh: "Zona A", "Zona B") |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

---

### 2. Inbound — Barang Masuk

#### `cycles` — Dokumen Penerimaan

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `supplier_id` | BIGINT (FK) | Supplier pengirim |
| `cycle_number` | INT | Nomor cycle per supplier (1, 2, 3...) |
| `status` | ENUM | `draft` → `receiving` → `completed` |
| `received_at` | TIMESTAMP | Waktu selesai penerimaan |
| `notes` | TEXT | Catatan |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Aturan:** Kombinasi `supplier_id` + `cycle_number` harus unik.  
> **Hapus:** Cycle tidak bisa dihapus jika supplier masih ada (RESTRICT).

#### `cycle_items` — Detail Item per Cycle

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `cycle_id` | BIGINT (FK) | ID cycle |
| `product_id` | BIGINT (FK) | Produk yang diterima |
| `quantity` | INT | Jumlah dari dokumen supplier |
| `received_quantity` | INT | Jumlah aktual diterima (setelah verifikasi) |
| `notes` | TEXT | Catatan (contoh: "2 pcs rusak") |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Aturan:** Satu produk hanya muncul sekali per cycle (`cycle_id` + `product_id` = UNIQUE).  
> **Hapus:** Kalau cycle dihapus → item ikut terhapus (CASCADE).

---

### 3. Inventory — Stok Gudang

#### `stocks` — Stok Real-time

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `product_id` | BIGINT (FK) | Produk |
| `rack_id` | BIGINT (FK) | Rak penyimpanan |
| `quantity` | INT | Jumlah stok saat ini |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Aturan:** Satu produk + satu rak = satu baris stok (`product_id` + `rack_id` = UNIQUE).  
> **Update:** `quantity` bertambah saat cycle selesai, berkurang saat shipment diproses.  
> **Hapus:** Tidak bisa dihapus jika produk/rak masih ada (RESTRICT).

---

### 4. Outbound — Barang Keluar

#### `shipments` — Dokumen Pengiriman

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `partner_name` | VARCHAR(255) | Nama mitra / client |
| `shipment_date` | DATE | Tanggal pengiriman |
| `status` | ENUM | `draft` → `shipped` → `completed` |
| `notes` | TEXT | Catatan |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

#### `shipment_items` — Detail Item per Shipment

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `shipment_id` | BIGINT (FK) | ID shipment |
| `product_id` | BIGINT (FK) | Produk yang dikirim |
| `rack_id` | BIGINT (FK) | Rak asal pengambilan |
| `quantity` | INT | Jumlah yang dikirim |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> **Hapus:** Kalau shipment dihapus → item ikut terhapus (CASCADE).

---

### 5. System

#### `users` — Pengguna Sistem

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `name` | VARCHAR(255) | Nama user |
| `email` | VARCHAR(255) UNIQUE | Email (untuk login) |
| `password` | VARCHAR(255) | Password (hashed) |
| `created_at` | TIMESTAMP | Tanggal dibuat |
| `updated_at` | TIMESTAMP | Tanggal diupdate |

> User memiliki role & permission melalui Spatie Laravel-Permission.  
> Tabel permission tidak ditampilkan karena auto-generated.

#### `menus` — Navigasi Sidebar

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `name` | VARCHAR(255) | Nama menu |
| `icon` | VARCHAR(255) | Nama komponen icon |
| `path` | VARCHAR(255) | URL path |
| `parent_id` | BIGINT (FK) | ID parent menu (untuk sub-menu) |
| `sort_order` | INT | Urutan tampilan |
| `permission_name` | VARCHAR(255) | Permission yang dibutuhkan |
| `group` | VARCHAR(255) | Grup: `main` atau `others` |

> Menu dikelola dinamis — sidebar otomatis menyesuaikan.  
> `parent_id` referensi ke `menus.id` sendiri (self-referencing).

#### `import_logs` — Log Import Excel

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | BIGINT (PK) | ID unik |
| `user_id` | BIGINT (FK) | User yang melakukan import |
| `model_type` | VARCHAR(255) | Model target (supplier, product, dll) |
| `file_name` | VARCHAR(255) | Nama file yang diimport |
| `file_format` | VARCHAR(255) | Format file (xlsx, csv) |
| `status` | VARCHAR(255) | `pending` / `processing` / `completed` / `failed` |
| `column_mapping` | JSON | Mapping kolom Excel → field database |
| `total_rows` | INT | Total baris di file |
| `processed_rows` | INT | Baris berhasil diimport |
| `skipped_rows` | INT | Baris dilewati (duplikat/error) |
| `errors` | JSON | Detail error jika gagal |

---

## Relasi Antar Tabel

```
suppliers ──┬── supplier_addresses  (1 supplier punya N alamat)
            ├── products            (1 supplier supply N produk)
            ├── cycles              (1 supplier punya N cycle)
            └── [shipments]         (tidak terkait langsung)

vehicle_models ──── products        (1 model punya N produk)

product_categories ──── products    (1 kategori punya N produk)

products ──┬── cycle_items          (1 produk muncul di N cycle)
            ├── stocks              (1 produk disimpan di N rak)
            └── shipment_items      (1 produk dikirim di N shipment)

racks ──┬── stocks                  (1 rak simpan N produk)
         └── shipment_items         (1 rak jadi sumber N pengiriman)

cycles ──── cycle_items             (1 cycle punya N item)

shipments ──── shipment_items       (1 shipment punya N item)
```

---

## Status Lifecycle

### Cycle (Penerimaan)

```
  ┌────────┐       ┌───────────┐       ┌───────────┐
  │ DRAFT  │  ───> │ RECEIVING │  ───> │ COMPLETED │
  └────────┘       └───────────┘       └───────────┘
  Cycle dibuat     Barang datang,      Penerimaan selesai
  (bisa diedit)    verifikasi qty      → stok bertambah
                   (bisa direceive)
```

### Shipment (Pengiriman)

```
  ┌────────┐       ┌──────────┐       ┌───────────┐
  │ DRAFT  │  ───> │ SHIPPED  │  ───> │ COMPLETED │
  └────────┘       └──────────┘       └───────────┘
  Shipment dibuat  Barang dikirim     Mitra terima
  (bisa diedit)    → stok berkurang
```

---

## Aturan Penghapusan Data

| Aturan | Tabel | Penjelasan |
|--------|-------|------------|
| **CASCADE** | `supplier_addresses` | Hapus supplier → alamat ikut terhapus |
| **CASCADE** | `cycle_items` | Hapus cycle → item ikut terhapus |
| **CASCADE** | `shipment_items` | Hapus shipment → item ikut terhapus |
| **RESTRICT** | `products` (FK ke categories/models/suppliers) | Tidak bisa hapus model/kategori/supplier jika masih ada produk terkait |
| **RESTRICT** | `cycles` (FK ke suppliers) | Tidak bisa hapus supplier jika masih ada cycle |
| **RESTRICT** | `stocks` (FK ke products/racks) | Tidak bisa hapus produk/rak jika masih ada stok |

> **Soft-delete:** Untuk produk, gunakan `is_active = false` daripada menghapus permanen.

---

> 📄 File DBML: `docs/database-schema.dbml` — bisa diimport ke [dbdiagram.io](https://dbdiagram.io) untuk generate diagram ERD visual.
