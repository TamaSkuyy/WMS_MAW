# Kamus Data — Warehouse Management System

## Mitra Adhi Wasana

> **Untuk:** Admin, Data Entry, Staff yang menginput data master  
> **Versi:** 1.0 — Juli 2026

---

## Daftar Isi

1. [Supplier](#1-supplier)
2. [Produk](#2-produk)
3. [Model Kendaraan](#3-model-kendaraan)
4. [Kategori Produk](#4-kategori-produk)
5. [Rak](#5-rak)
6. [Cycle (Penerimaan)](#6-cycle-penerimaan)
7. [Shopping (Pengeluaran)](#7-shopping-pengeluaran)
8. [Delivery Slot](#8-delivery-slot)

---

## 1. Supplier

**Menu:** `Master Data > Suppliers`  
**Import:** `suppliers/import` — Template: `Kode, Nama, Kontak, Email, Telepon, Jalan, Kota, Provinsi, KodePos, Negara`

### Form Tambah / Edit Supplier

| # | Field | Label | Wajib | Tipe | Max | Keterangan | Contoh |
|---|-------|-------|-------|------|-----|-----------|--------|
| 1 | `code` | Kode / Singkatan | ❌ | Text | 10 | Singkatan unik. Auto-generate dari nama jika dikosongkan. Huruf kapital otomatis | `DWA`, `MMM`, `SMA` |
| 2 | `name` | Nama | ✅ | Text | 255 | Nama perusahaan supplier. Harus unik | `PT. Dwi Warna Abadi` |
| 3 | `contact_person` | Kontak Person | ❌ | Text | 255 | Nama orang yang bisa dihubungi | `Bpk. Andi` |
| 4 | `email` | Email | ✅ | Email | 255 | Email valid & unik | `andi@dwa.co.id` |
| 5 | `phone` | Telepon | ❌ | Text | 20 | Nomor telepon | `+62812345678` |

### Alamat (Primary)

| # | Field | Label | Wajib | Tipe | Max | Contoh |
|---|-------|-------|-------|------|-----|--------|
| 6 | `street` | Jalan | ✅ | Text | 255 | `Jl. Raya Industri No. 12` |
| 7 | `city` | Kota | ✅ | Text | 100 | `Jakarta` |
| 8 | `state` | Provinsi | ✅ | Text | 100 | `DKI Jakarta` |
| 9 | `postal_code` | Kode Pos | ✅ | Text | 20 | `12345` |
| 10 | `country` | Negara | ✅ | Text | 100 | `Indonesia` |

### Jadwal Pengiriman (Edit Only)

| # | Field | Label | Keterangan |
|---|-------|-------|-----------|
| 11 | `delivery_slot_ids` | Slot Harian | Checkbox multi-select C1–C6. Pilih slot waktu pengiriman rutin supplier ini |

---

## 2. Produk

**Menu:** `Master Data > Products`  
**Import:** `products/import` — Template: `PartNumber, Nama, Merek, Model, Supplier, Kategori, Satuan, Deskripsi, Aktif, Rak`

### Form Tambah / Edit Produk

| # | Field | Label | Wajib | Tipe | Max | Keterangan | Contoh |
|---|-------|-------|-------|------|-----|-----------|--------|
| 1 | `part_number` | Part Number | ✅ | Text | 50 | Kode unik produk. Tidak boleh duplikat | `P5188-0KA03` |
| 2 | `name` | Nama Produk | ✅ | Text | 255 | Nama deskriptif produk | `Grade Emblem (VRZ)` |
| 3 | `vehicle_model_id` | Model Kendaraan | ✅ | Select | — | Pilih dari daftar model yang sudah ada | `Fortuner VRZ` |
| 4 | `supplier_id` | Supplier | ✅ | Select | — | Pilih supplier pemasok | `DWA — PT. Dwi Warna` |
| 5 | `category_id` | Kategori | ✅ | Select | — | Pilih kategori produk | `Electrical` |
| 6 | `unit` | Satuan | ✅ | Text | 20 | Satuan barang | `pcs`, `box`, `set` |
| 7 | `description` | Deskripsi | ❌ | Textarea | 1000 | Keterangan tambahan | `Orisinil Toyota Genuine Parts` |
| 8 | `is_active` | Aktif | ❌ | Checkbox | — | Default: aktif. Nonaktifkan jika produk sudah tidak digunakan | ✅ |
| 9 | `default_rack_id` | Rak Default | ❌ | Select | — | Rak default tempat menyimpan produk ini | `A-01` |

---

## 3. Model Kendaraan

**Menu:** `Master Data > Model Kendaraan`  
**Import:** `vehicle-models/import` — Template: `Nama, Suffix`

> Merek otomatis **Toyota** (default). Tidak perlu diisi manual.

### Form Tambah / Edit Model

| # | Field | Label | Wajib | Tipe | Max | Keterangan | Contoh |
|---|-------|-------|-------|------|-----|-----------|--------|
| 1 | `name` | Model | ✅ | Text | 100 | Nama model kendaraan | `Fortuner`, `Avanza`, `Yaris` |
| 2 | `suffix` | Suffix (Varian) | ❌ | Text | 50 | Varian model. Otomatis huruf kapital | `VRZ`, `GR`, `TRD` |

**Kombinasi unik:** `(name, brand, suffix)` — tidak boleh ada duplikat kombinasi yang sama.

---

## 4. Kategori Produk

**Menu:** `Master Data > Kategori Produk`  
**Import:** `product-categories/import`

### Form Tambah / Edit Kategori

| # | Field | Label | Wajib | Tipe | Max | Contoh |
|---|-------|-------|-------|------|-----|--------|
| 1 | `name` | Nama | ✅ | Text | 255 | `Body Parts`, `Electrical`, `Engine` |
| 2 | `description` | Deskripsi | ❌ | Textarea | — | `Komponen body kendaraan Toyota` |

---

## 5. Rak

**Menu:** `Master Data > Racks`  
**Import:** `racks/import`

### Form Tambah / Edit Rak

| # | Field | Label | Wajib | Tipe | Max | Keterangan | Contoh |
|---|-------|-------|-------|------|-----|-----------|--------|
| 1 | `code` | Kode Rak | ✅ | Text | 50 | Kode unik rak | `A-01`, `B-03` |
| 2 | `zone` | Zona | ✅ | Text | 50 | Zona/wilayah gudang | `A`, `B`, `C` |
| 3 | `description` | Deskripsi | ❌ | Text | — | Lokasi atau fungsi rak | `Rak dekat pintu masuk` |

---

## 6. Cycle (Penerimaan)

**Menu:** `Transactions > Receiving`

### Form Buat Cycle

| # | Field | Label | Wajib | Tipe | Keterangan | Contoh |
|---|-------|-------|-------|------|-----------|--------|
| 1 | `supplier_id` | Supplier | ✅ | Select | Pilih supplier | `DWA` |
| 2 | `items[].product_id` | Produk | ✅ | Select | Pilih produk yang akan diterima | `P5188-0KA03` |
| 3 | `items[].quantity` | Qty Rencana | ✅ | Number | Minimal 1 | `100` |
| 4 | `notes` | Catatan | ❌ | Textarea | Max 500 karakter | `Pengiriman reguler` |

### Form Receive (Terima Barang)

| # | Field | Label | Wajib | Tipe | Keterangan |
|---|-------|-------|-------|------|-----------|
| 1 | `items[].id` | Item ID | ✅ | Hidden | ID item dari cycle |
| 2 | `items[].received_quantity` | Qty Diterima | ✅ | Number | Min 0. Boleh kurang/lebih dari rencana |
| 3 | `items[].rack_id` | Rak Simpan | ✅ | Select | Rak fisik tempat barang disimpan |
| 4 | `items[].notes` | Catatan | ❌ | Text | Max 200. Alasan selisih (rusak, dll) |

### Form Quick Receive

| # | Field | Label | Wajib | Tipe | Keterangan |
|---|-------|-------|-------|------|-----------|
| 1 | `supplier_id` | Supplier | ✅ | Select | |
| 2 | `items[].product_id` | Produk | ✅ | Select | |
| 3 | `items[].rack_id` | Rak | ✅ | Select | |
| 4 | `items[].quantity` | Qty | ✅ | Number | Min 1. Sekaligus sebagai qty diterima |

---

## 7. Shopping (Pengeluaran)

**Menu:** `Transactions > Shopping`

### Form Buat Shopping

| # | Field | Label | Wajib | Tipe | Keterangan |
|---|-------|-------|-------|------|-----------|
| 1 | `partner_name` | Partner / Penerima | ✅ | Text | Nama penerima barang |
| 2 | `items[].product_id` | Produk | ✅ | Select | |
| 3 | `items[].quantity` | Qty Rencana | ✅ | Number | Min 1 |
| 4 | `notes` | Catatan | ❌ | Text | |

### Form Ship (Kirim Barang)

| # | Field | Label | Wajib | Tipe | Keterangan |
|---|-------|-------|-------|------|-----------|
| 1 | `items[].id` | Item ID | ✅ | Hidden | |
| 2 | `items[].shipped_quantity` | Qty Dikirim | ✅ | Number | Min 0. Max ≤ stok tersedia |

---

## 8. Delivery Slot

**Menu:** `Master Data > Jadwal Slot Pengiriman`

> Jumlah slot fixed 6 (C1–C6). Hanya bisa edit waktu & label, tidak bisa tambah/hapus.

### Form Edit Slot

| # | Field | Label | Wajib | Tipe | Format | Contoh |
|---|-------|-------|-------|------|--------|--------|
| 1 | `time_start` | Jam Mulai | ✅ | Time | `HH:MM` (24 jam) | `07:30` |
| 2 | `time_end` | Jam Selesai | ✅ | Time | `HH:MM` (24 jam). Harus > `time_start` | `09:30` |
| 3 | `label` | Label | ❌ | Text | Max 255 | `Pagi 1`, `Siang 2` |

### Default Slot (dari migration)
| Slot | Mulai | Selesai | Label |
|------|-------|---------|-------|
| C1 | 07:30 | 09:30 | Pagi 1 |
| C2 | 09:30 | 11:30 | Pagi 2 |
| C3 | 11:30 | 13:30 | Siang 1 |
| C4 | 13:30 | 15:30 | Siang 2 |
| C5 | 15:30 | 17:30 | Sore 1 |
| C6 | 17:30 | 19:30 | Sore 2 |

---

## Ringkasan Format Kode

| Entitas | Format | Contoh | Auto? |
|---------|--------|--------|-------|
| Part Number | `P<4angka>-<2huruf><3angka>` | `P5188-0KA03` | Manual |
| Supplier Code | 2-3 huruf kapital + optional angka | `DWA`, `AJM2` | Auto (jika kosong) |
| Rak Code | `<huruf>-<2angka>` | `A-01` | Manual |
| Cycle Number | Integer urut per supplier | `1`, `2`, `3`... | Auto |
| Slot | `C1` s/d `C6` | Fixed | System |

---

> 💡 **Tip Data Entry:**
> - Part Number harus unik — cek dulu sebelum input
> - Supplier Code sebaiknya diisi manual (2-3 huruf) agar sesuai dengan singkatan asli
> - Nama produk dibuat deskriptif — sertakan varian/model dalam nama
> - Pastikan Supplier, Model, Kategori, dan Rak sudah terdaftar sebelum input Produk

> 📅 Diperbarui: Juli 2026
