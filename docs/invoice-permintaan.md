# 🧾 Draf Tagihan (Invoice) — Permintaan Major & Minor

**Proyek:** Warehouse Management System (WMS) — Mitra Adhi Wasana
**Dasar perhitungan:** `docs/request-log.md` (rekonstruksi dari 143 commit + 25 spec)
**Periode pekerjaan:** 2026-05-16 s/d 2026-09-18

| Field | Isi |
|-------|-----|
| Nomor Invoice | `INV/____/____` |
| Tanggal Invoice | `____` |
| Ditagihkan oleh (Dari) | `________________` |
| Ditagihkan kepada (Kepada) | `________________` |
| Jatuh Tempo | `____` |
| No. Kontrak / SPK | `________________` |

---

## 1. Ringkasan Item

| Kategori | Jumlah Item | Ditagihkan? |
|----------|-------------|-------------|
| 🟠 **Major** (fitur/modul baru & perubahan alur) | **42** | ✅ Ya |
| 🟡 **Minor** (penyempurnaan, UI/UX, label, branding) | **34** | ✅ Ya |
| **TOTAL item permintaan** | **76** | ✅ Ya |
| ⚙️ Internal (infra, deploy, refactor, tooling) | 19 | ❌ Tidak |
| ⚪ Enhancement (di luar kesepakatan awal) | 0 | ❌ Tidak ada |

> **Cara hitung:** 1 item permintaan = **1 unit pekerjaan**. Harga satuan
> diisi pada §4. Total baris = 76 unit.

---

## 2. 🟠 Rincian Item MAJOR — 42 item

| No | Tanggal | Item Pekerjaan | Jumlah | Keterangan |
|----|---------|----------------|:------:|------------|
| 1 | 2026-05-16 | Fondasi proyek WMS (Laravel + Inertia + React) | 1 | ⚠️ cek lingkup awal |
| 2 | 2026-05-17 | RBAC awal + menu dinamis + notifikasi realtime | 1 | ⚠️ cek lingkup awal |
| 3 | 2026-06-04 | Modul inti: Produk, Supplier, Rak, Cycle + import/export | 1 | ⚠️ cek lingkup awal |
| 4 | 2026-06-11 | Modul Kategori Produk & Model Kendaraan + multi-bahasa | 1 | |
| 5 | 2026-06-15 | Modul Organisasi: Jabatan, Lokasi Kerja, Departemen, Karyawan | 1 | |
| 6 | 2026-06-28 | Suffix pada Model Kendaraan + filter produk per supplier | 1 | |
| 7 | 2026-06-29 | QR Quick Receive (scan saat penerimaan) | 1 | |
| 8 | 2026-06-29 | Default rak di produk + prefill pintar penerimaan | 1 | |
| 9 | 2026-06-29 | Unit + Suffix trigger di Shopping | 1 | |
| 10 | 2026-07-05 | Proteksi input transaksi (locking & idempotensi) | 1 | |
| 11 | 2026-07-06 | TV Dashboard realtime (auto-rotate slide) | 1 | |
| 12 | 2026-07-07 | Modul Laporan Transaksi (Receiving & Shopping) + export Excel/PDF | 1 | |
| 13 | 2026-07-08 | Import/Export generik untuk semua master data | 1 | |
| 14 | 2026-07-13 | Modul Shift | 1 | |
| 15 | 2026-07-14 | Delivery Monitor — dashboard pengiriman supplier | 1 | |
| 16 | 2026-07-15 | Delivery Monitor data nyata + live sync + slot pengiriman | 1 | |
| 17 | 2026-07-15 | Supplier Ledger (auto-scroll + realtime) | 1 | |
| 18 | 2026-07-26 | Rak nullable = RELAY/overflow + wajib pilih rak | 1 | |
| 19 | 2026-07-29 | Modul Lokasi Shopping + monitoring stok di produk | 1 | |
| 20 | 2026-07-29 | Laporan performa supplier + receive logs | 1 | |
| 21 | 2026-07-29 | RBAC penuh + mapping JobPosition ↔ Role | 1 | |
| 22 | 2026-07-31 | Nomor frame + scan barcode di Shopping | 1 | |
| 23 | 2026-07-31 | Kapasitas rak + alert di dashboard | 1 | |
| 24 | 2026-08-06/07 | RBAC granular per menu & CRUD | 1 | |
| 25 | 2026-08-18 | Import Shopping dari Excel (SharePoint) | 1 | |
| 26 | 2026-08-21 | Dashboard performa operator realtime | 1 | |
| 27 | 2026-08-21 | Export laporan performa supplier (Excel/PDF) | 1 | |
| 28 | 2026-08-28 | Flag `is_cripple` pada Shopping | 1 | |
| 29 | 2026-08-30 | `carrier_id` / PIC pada Cycle | 1 | |
| 30 | 2026-09-01 | Bulk Ship — kirim massal draft shoppings | 1 | |
| 31 | 2026-09-03 | Stock correction untuk Cycle & Shopping | 1 | |
| 32 | 2026-09-03 | **Modul Stock Opname** | 1 | contoh Major |
| 33 | 2026-09-04 | Data Reset (web + CLI) | 1 | |
| 34 | 2026-09-04 | Log Viewer + permission & menu | 1 | |
| 35 | 2026-09-08 | Penomoran cycle harian per supplier | 1 | |
| 36 | 2026-09-16 | Bulk delete transaksi + koreksi stok otomatis | 1 | |
| 37 | 2026-09-16 | Scan barcode di pencarian & form pilih produk | 1 | |
| 38 | 2026-09-17 | Alur Import Shopping 2 langkah (header → barang) | 1 | |
| 39 | 2026-09-17 | Feature toggle (on/off) via menu Pengaturan | 1 | |
| 40 | 2026-09-17/18 | StockLedger + StockRepairService + perintah CLI stok | 1 | |
| 41 | 2026-09-18 | Bulk Ship preview (planner stok, mode `only_ready`) | 1 | |
| 42 | 2026-09-18 | Pencarian & filter Inventori Stok lengkap | 1 | |
| | | **Subtotal Major** | **42** | |

---

## 3. 🟡 Rincian Item MINOR — 33 item

| No | Tanggal | Item Pekerjaan | Jumlah | Keterangan |
|----|---------|----------------|:------:|------------|
| 1 | 2026-05-27 | Redesign UI frontend (tema Tailadmin) | 1 | skala besar, non-fungsional |
| 2 | 2026-05-27 | Brand color *Logistics Blue* | 1 | |
| 3 | 2026-06-19 | Standarisasi komponen & layout halaman | 1 | |
| 4 | 2026-07-13 | Rename **Shipment → Shopping** (istilah) | 1 | |
| 5 | 2026-07-13 | Rebrand logo MAW | 1 | |
| 6 | 2026-07-13 | Warna stat card laporan Receiving & Shopping | 1 | |
| 7 | 2026-07-14 | Sembunyikan harga produk (`base_price`) | 1 | |
| 8 | 2026-07-14 | Layout per-halaman + batasan data harga/model | 1 | |
| 9 | 2026-07-15 | Nomor cycle otomatis di server (hapus input manual) | 1 | |
| 10 | 2026-07-22 | Branding "Mitra Adhi Wasana" + quick link Delivery Monitor | 1 | |
| 11 | 2026-07-22 | Ganti ikon brand → logo perusahaan | 1 | |
| 12 | 2026-07-24 | Model kendaraan default Toyota + field kode Supplier | 1 | |
| 13 | 2026-07-24 | Filter produk per supplier di modul Cycle | 1 | |
| 14 | 2026-07-27 | Layout tabel transaksi mobile + sidebar auto-close | 1 | |
| 15 | 2026-07-29 | Daftar cycle selesai + rata-rata durasi di dashboard | 1 | |
| 16 | 2026-08-02 | Format tanggal seragam `dd/mm/yyyy hh:mm:ss` | 1 | |
| 17 | 2026-08-05 | Shopping boleh dibuat/diubah tanpa item | 1 | |
| 18 | 2026-08-13 | Desain UI/UX modul Shopping (Create/Edit + konsistensi) | 1 | |
| 19 | 2026-08-14 | Redesign antarmuka Shopping + komponen alert & input | 1 | |
| 20 | 2026-08-14/15 | UI mobile-friendly Receiving & Shopping | 1 | |
| 21 | 2026-08-15 | Barcode pada Lokasi Shopping + scan lokasi | 1 | |
| 22 | 2026-08-17 | Tracking pengirim + timestamp pada shopping | 1 | |
| 23 | 2026-08-18 | Polling fallback 15 detik Delivery Monitor | 1 | |
| 24 | 2026-08-19 | Komponen Pagination reusable | 1 | |
| 25 | 2026-08-25 | Audit trail `created_by` / `updated_by` | 1 | |
| 26 | 2026-08-30 | Import shopping tanpa `shopping_location_id` | 1 | |
| 27 | 2026-08-31 | Perbaikan parsing tanggal ShoppingImporter | 1 | |
| 28 | 2026-09-16 | Pencarian cycle by supplier / kode / part / nomor cycle | 1 | |
| 29 | 2026-09-16 | `shopping_date` → `datetime` (akurasi jam) | 1 | |
| 30 | 2026-09-17 | Kolom Model & Suffix opsional di Shopping + laporan | 1 | |
| 31 | 2026-09-18 | Search Shopping termasuk nomor frame + perbaikan label | 1 | |
| 32 | 2026-09-18 | Perbaikan input frame number (scan barcode) | 1 | |
| 33 | 2026-09-18 | Permission `import shoppings` — import khusus Leader ke atas | 1 | hak akses |
| 34 | 2026-09-18 | Overhaul UI/UX Input Header Shopping mobile-friendly (kartu per frame, footer sticky, tempel clipboard) | 1 | |
| | | **Subtotal Minor** | **34** | |

---

## 4. Perhitungan (diisi kemudian)

| Kategori | Jumlah Item | Harga Satuan | Subtotal |
|----------|:-----------:|--------------|----------|
| 🟠 Major | 42 | `____________` | `____________` |
| 🟡 Minor | 34 | `____________` | `____________` |
| **Jumlah** | **76** | | **`____________`** |
| Diskon | | | `____________` |
| PPN (jika ada) | | | `____________` |
| **Total Tagihan** | | | **`____________`** |

**Terbilang:** `________________________________________________`

---

## 5. Tidak Ditagihkan — Internal (19 item)

Infra, deploy, refactor, tooling, dan dokumentasi — **bukan permintaan user**:

`.superpowers` gitignore · update deploy script produksi (Jun) · artisan import produk + bersihkan data dummy · perbaikan deploy script & Reverb · backup + health monitoring + activity log · perbaikan upgrade Activitylog v5 · upgrade base image FrankenPHP · konsolidasi seeder permission/role · perbaikan CSP pagination & DatePicker · seeder karyawan produksi · timeout ProcessImport + health check queue worker · konfigurasi backup DB + MySQL dump · refactor format Cycle Index · healthcheck endpoint + Docker healthcheck · data purge cycle completed & shopping shipped · optimasi memori + `ExportException` · backup health check · deploy lock + nginx buffer/large header · manual book import + update dokumentasi.

---

## 6. Catatan Sebelum Dikirim

1. ⚠️ **Cek lingkup kontrak awal.** Item Major no. 1–3 (fondasi proyek, RBAC awal, modul inti) dan beberapa modul master (no. 4, 5) kemungkinan **sudah termasuk kontrak awal** — coret dulu kalau tidak mau dobel tagih. Kolom Keterangan sudah ditandai.
2. **Item abu-abu** yang bisa dipindah kategori sesuai kesepakatan: Minor no. 1 (redesign UI, skala besar), Minor no. 33 (permission import), dan Minor no. 34 (overhaul mobile Input Header).
3. **Perbaikan bug yang muncul dari perubahan sendiri** idealnya tidak ditagih terpisah — sudah digabung ke item terkait atau masuk §5.
4. **Dasar perhitungan transparan:** tiap item bisa dilacak ke spec/commit di
   `docs/request-log.md` (kolom Sumber). Lampirkan dokumen itu bersama invoice.
5. Nomor invoice, tanggal, identitas pihak, dan harga belum diisi — silakan lengkapi §Header & §4.

---

*Dibuat dari `docs/request-log.md`. Total item permintaan: **76 (42 Major + 34 Minor)**.*
