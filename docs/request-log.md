# 📋 Log Permintaan (Request Log) — WMS MAW

**Untuk:** tim developer & PIC user
**Dibuat:** 2026-09-18
**Sumber:** rekonstruksi dari riwayat git (143 commit), `docs/superpowers/specs/` (25 file), `docs/superpowers/plans/` (22 file), dan komentar kode yang menyebut asal permintaan ("permintaan pusat/TAM", "permintaan operator").

> ⚠️ **Catatan penting:** dokumen ini **bukan catatan resmi dari user**. Repo ini
> tidak punya changelog / issue tracker / log permintaan. Isi di bawah adalah
> **rekonstruksi** dari spec & commit. Kalau ada permintaan user yang tidak
> tercatat di spec/commit, tambahkan manual di sini. Kategori bisa bergeser
> tergantung kesepakatan.

---

## 1. Kriteria Klasifikasi

Mengacu ke definisi severity di `docs/uat.md` §6.3, disesuaikan untuk **permintaan/perubahan**, bukan temuan UAT:

| Kategori | Definisi | Contoh |
|----------|----------|--------|
| 🟠 **Major** | Fitur / modul **baru**, alur kerja **baru**, atau perubahan skema data / aturan bisnis / hak akses yang mengubah cara kerja user | Modul Stock Opname, Import 2 langkah, Bulk Ship |
| 🟡 **Minor** | Penyempurnaan UI/UX, label/teks, field opsional, validasi kecil, istilah, atau branding — **tidak** menambah fungsi inti | Rename Shipment → Shopping, sembunyikan harga produk, warna stat card |
| ⚪ **Enhancement** | Usulan pengembangan **di luar kesepakatan awal** (belum ada yang tercatat) | — |
| ⚙️ **Internal** | Infra, deploy, refactor, tooling, dokumentasi — **bukan request user langsung** (lihat Lampiran) | nginx, Docker, seeder, optimasi memori |

---

## 2. Ringkasan

| Kategori | Jumlah |
|----------|--------|
| 🟠 Major | 42 |
| 🟡 Minor | 34 |
| ⚪ Enhancement | 0 (belum tercatat) |
| ⚙️ Internal | 19 |

---

## 3. 🟠 MAJOR — Fitur / Modul Baru & Perubahan Alur

| # | Tanggal | Permintaan / Perubahan | Modul | Sumber |
|---|---------|------------------------|-------|--------|
| 1 | 2026-05-16 | Fondasi proyek WMS (Laravel + Inertia + React) | Sistem | commit `4898b99` |
| 2 | 2026-05-17 | RBAC awal + menu dinamis + notifikasi realtime (Reverb) | Sistem | commit `eb32eb1` |
| 3 | 2026-06-04 | Modul inti: Produk, Supplier, Rak, Cycle + layer import/export | Master & Transaksi | commit `d87b242`; `specs/2026-06-04-*` |
| 4 | 2026-06-11 | Modul Kategori Produk & Model Kendaraan + multi-bahasa/lokalisasi UI | Master | commit `4e200ae` |
| 5 | 2026-06-15 | Modul Organisasi: Jabatan, Lokasi Kerja, Departemen, Karyawan | Master | commit `00728f8` |
| 6 | 2026-06-28 | Suffix pada Model Kendaraan + filter produk per supplier di pengiriman | Master / Shopping | `specs/2026-06-28-suffix-vehicle-model-design.md` |
| 7 | 2026-06-29 | QR Quick Receive (scan saat penerimaan) | Penerimaan | `specs/2026-06-29-qr-quick-receive-design.md`; commit `fc23fd2` |
| 8 | 2026-06-29 | Default rak di produk + prefill pintar saat penerimaan | Penerimaan | `specs/2026-06-29-default-rack-product-receiving-design.md` |
| 9 | 2026-06-29 | Unit + Suffix trigger di Shopping | Shopping | `specs/2026-06-29-shopping-unit-suffix-trigger-design.md`; commit `4380b51` |
| 10 | 2026-07-05 | Proteksi input transaksi (locking & idempotensi) | Transaksi | `specs/2026-07-05-transaction-input-protection-design.md`; commit `6d9b671` |
| 11 | 2026-07-06 | TV Dashboard realtime (auto-rotate slide) | Monitoring | `specs/2026-07-06-tv-dashboard-design.md`; commit `055a425` |
| 12 | 2026-07-07 | Modul Laporan Transaksi (Receiving & Shopping) + export Excel/PDF | Laporan | `specs/2026-07-07-transaction-report-design.md`; commit `bf349b9` |
| 13 | 2026-07-08 | Import/Export generik untuk semua master data | Import/Export | `specs/2026-05-31-import-export-design.md`, `specs/2026-07-08-master-data-import-export-design.md`; commit `25ba80c` |
| 14 | 2026-07-13 | Modul Shift | Master | `specs/2026-07-13-shift-master-design.md`; commit `0a002ba` |
| 15 | 2026-07-14 | Delivery Monitor — dashboard pengiriman supplier | Monitoring | `specs/2026-07-14-delivery-monitor-design.md`; commit `cb9251d` |
| 16 | 2026-07-15 | Delivery Monitor data nyata + live sync (Echo) + slot pengiriman | Monitoring | `specs/2026-07-15-delivery-monitor-real-data-design.md`; commit `b02a237`, `5615b46` |
| 17 | 2026-07-15 | Supplier Ledger (daftar part auto-scroll + realtime) | Monitoring | commit `ab86432` |
| 18 | 2026-07-26 | Rak nullable = RELAY/overflow + wajib pilih rak di cycle & shopping | Stok / Transaksi | commit `e2078c1`, `237c604` |
| 19 | 2026-07-29 | Modul Lokasi Shopping + monitoring stok di produk | Master | commit `2087998`, `d7ca641` |
| 20 | 2026-07-29 | Laporan performa supplier + receive logs | Laporan | commit `e72c2db` |
| 21 | 2026-07-29 | RBAC penuh + mapping JobPosition ↔ Role | Sistem | `specs/2026-07-29-role-based-access-control-design.md`; commit `8d60a93`, `1d64699` |
| 22 | 2026-07-31 | Nomor frame + scan barcode di Shopping | Shopping | commit `e99998e` |
| 23 | 2026-07-31 | Kapasitas rak + alert di dashboard | Master / Monitoring | commit `68a0f78` |
| 24 | 2026-08-06/07 | RBAC granular per menu & CRUD (supplier dulu, lalu semua master) | Sistem | commit `50f1569`, `338a517`, `53564bd`, `cc0e417` |
| 25 | 2026-08-18 | Import Shopping dari Excel (SharePoint) — grouping frame + merge draft | Import / Shopping | `specs/2026-08-18-shopping-sharepoint-import-design.md`; commit `c660043` |
| 26 | 2026-08-21 | Dashboard performa operator realtime (shopping & receiving) | Monitoring | commit `4562893` |
| 27 | 2026-08-21 | Export laporan performa supplier (Excel/PDF) | Laporan | commit `781b4b5` |
| 28 | 2026-08-28 | Flag `is_cripple` pada Shopping | Shopping | commit `91e7c98` |
| 29 | 2026-08-30 | `carrier_id` / PIC pada Cycle | Penerimaan | commit `10171e6` |
| 30 | 2026-09-01 | Bulk Ship — kirim massal draft shoppings | Shopping | commit `a1518b2` |
| 31 | 2026-09-03 | Stock correction untuk Cycle & Shopping | Stok | commit `21cd7cc` |
| 32 | 2026-09-03 | **Modul Stock Opname** (contoh Major) | Stok | commit `443e2d6`, `0ee46e5` |
| 33 | 2026-09-04 | Data Reset (web + CLI) | Sistem | commit `322ab6f`, `6055d54` |
| 34 | 2026-09-04 | Log Viewer + permission & menu | Sistem | commit `c45acb9`, `800f72e` |
| 35 | 2026-09-08 | Penomoran cycle harian per supplier | Penerimaan | commit `b90b006`, `6ac9221` |
| 36 | 2026-09-16 | Bulk delete transaksi + koreksi stok otomatis | Transaksi | commit `05b774d` |
| 37 | 2026-09-16 | Scan barcode di input pencarian & form pilih produk | UX / Transaksi | commit `3fc4f95` |
| 38 | 2026-09-17 | Alur Import Shopping 2 langkah (header → barang) | Import / Shopping | commit `fb25b08` |
| 39 | 2026-09-17 | Feature toggle (on/off) via menu Pengaturan | Sistem | commit `a9dd501` |
| 40 | 2026-09-17/18 | StockLedger + StockRepairService + `stocks:audit/merge-duplicates/fix-phantom-relay/set-quantity` | Stok | commit `5787c12`, `3c318d9` |
| 41 | 2026-09-18 | Bulk Ship preview (planner stok, mode `only_ready`) | Shopping | commit `a7c30f4` |
| 42 | 2026-09-18 | Pencarian & filter Inventori Stok lengkap | Stok | commit `78807c7` |

---

## 4. 🟡 MINOR — Penyempurnaan, UI/UX, Label, Branding

| # | Tanggal | Permintaan / Perubahan | Modul | Sumber |
|---|---------|------------------------|-------|--------|
| 1 | 2026-05-27 | Redesign UI frontend (tema Tailadmin) — skala besar tapi non-fungsional | UI | `specs/2026-05-27-wms-frontend-redesign.md` |
| 2 | 2026-05-27 | Brand color *Logistics Blue* | UI | commit `dee57a1` |
| 3 | 2026-06-19 | Standarisasi komponen & layout halaman master/transaksi | UI | commit `cbdbcbf` |
| 4 | 2026-07-13 | Rename **Shipment → Shopping** (istilah) | Istilah | `specs/2026-07-13-shipment-to-shopping-rename-design.md`; commit `0a002ba`, `cc6a36d` |
| 5 | 2026-07-13 | Rebrand logo MAW | Branding | `specs/2026-07-13-maw-logo-rebrand-design.md`; commit `88f0282` |
| 6 | 2026-07-13 | Warna stat card laporan Receiving & Shopping | UI | `specs/2026-07-13-report-stat-cards-color-design.md` |
| 7 | 2026-07-14 | Sembunyikan harga produk (`base_price`) | Master / UI | `specs/2026-07-14-product-price-hide-design.md` |
| 8 | 2026-07-14 | Layout per-halaman + batasan data harga/model kendaraan | UI | commit `e9cab8c` |
| 9 | 2026-07-15 | Nomor cycle otomatis di server (hapus input manual) | Penerimaan | commit `d405ba7` |
| 10 | 2026-07-22 | Branding "Mitra Adhi Wasana" + quick link Delivery Monitor di dashboard | Branding | commit `139214a` |
| 11 | 2026-07-22 | Ganti ikon brand → logo perusahaan di halaman login/register | Branding | commit `88f0282` |
| 12 | 2026-07-24 | Model kendaraan default brand Toyota + field kode di Supplier | Master | commit `97c7a18` |
| 13 | 2026-07-24 | Filter produk per supplier di modul Cycle | Penerimaan | commit `9cc8c7b` |
| 14 | 2026-07-27 | Layout tabel transaksi mobile + sidebar auto-close saat navigasi | UI | commit `efc0310` |
| 15 | 2026-07-29 | Daftar cycle selesai + rata-rata durasi harian di dashboard | Dashboard | commit `1f0f26f` |
| 16 | 2026-08-02 | Format tanggal seragam `dd/mm/yyyy hh:mm:ss` | UI | commit `220573d` |
| 17 | 2026-08-05 | Shopping boleh dibuat/diubah tanpa item (untuk draft header) | Shopping | commit `35639d5` |
| 18 | 2026-08-13 | Desain UI/UX modul Shopping (Create/Edit + konsistensi Index/Show) | UI | `specs/2026-08-13-shopping-uiux-design.md` |
| 19 | 2026-08-14 | Redesign antarmuka Shopping + komponen alert & input | UI | commit `b5b2bb9` |
| 20 | 2026-08-14/15 | UI mobile-friendly Receiving & Shopping (kartu responsif + QtyStepper) | UI | `specs/2026-08-14-receiving-mobile-uiux-design.md`, `specs/2026-08-14-shopping-mobile-uiux-design.md`; commit `39bafaf` |
| 21 | 2026-08-15 | Barcode pada Lokasi Shopping + scan lokasi saat transaksi | Master / UX | commit `cff7fb2` |
| 22 | 2026-08-17 | Tracking pengirim + timestamp pada shopping | Shopping | commit `701c92c` |
| 23 | 2026-08-18 | Polling fallback 15 detik untuk Delivery Monitor | Monitoring | commit `1a83b1e` |
| 24 | 2026-08-19 | Komponen Pagination reusable | UI | commit `49c9c06` |
| 25 | 2026-08-25 | Audit trail `created_by` / `updated_by` | Sistem | commit `7a16d2d` |
| 26 | 2026-08-30 | Import shopping tanpa `shopping_location_id` (data TAM) | Import | commit `3a0b6ac` |
| 27 | 2026-08-31 | Perbaikan parsing tanggal ShoppingImporter | Import | commit `f198f53` |
| 28 | 2026-09-16 | Pencarian cycle by nama supplier / kode / part / nomor cycle | Penerimaan | commit `39e36d4` |
| 29 | 2026-09-16 | `shopping_date` → `datetime` (akurasi jam) | Shopping | commit `b2dd892` |
| 30 | 2026-09-17 | Kolom Model & Suffix opsional di Shopping + laporan/export | Shopping | commit `4f03d71`, `3fcfa18` |
| 31 | 2026-09-18 | Search Shopping termasuk nomor frame + perbaikan label UI | Shopping | commit `842f36b` |
| 32 | 2026-09-18 | Perbaikan input frame number (scan barcode & manajemen baris) | UI | commit `6c8394c` |
| 33 | 2026-09-18 | Permission `import shoppings` — Import Barang & Import Gabungan hanya Leader ke atas | Hak akses | commit `5470191` |
| 34 | 2026-09-18 | Overhaul UI/UX **Input Header Shopping** mobile-friendly (kartu per frame di HP, footer aksi sticky, tombol tempel clipboard, target sentuh 44px) | UI / Shopping | `specs/2026-09-18-shopping-header-mobile-uiux-design.md` |

> Catatan: item no. 1 (redesign UI), no. 33 (hak akses), dan no. 34 (overhaul mobile
> Input Header) adalah yang paling mungkin dipindah kategori. Redesign/overhaul =
> skala besar tapi tanpa fungsi baru; permission = kecil tapi mengubah siapa yang
> boleh bekerja.

---

## 5. ⚪ ENHANCEMENT

Belum ada yang tercatat. Definisi dari `docs/uat.md` §6.3: *saran perbaikan /
pengembangan baru di luar kesepakatan awal*.

| # | Tanggal | Usulan | Asal | Status |
|---|---------|--------|------|--------|
| — | — | — | — | — |

> Kalau UAT dijalankan, isi kolom **Log Temuan** (`docs/uat.md` §8) dengan
> temuan Critical/Major/Minor, lalu usulan baru dicatat di sini sebagai
> Enhancement.

---

## 6. ⚙️ Lampiran — Internal / Infra / Tooling (bukan request user)

Dicatat supaya riwayat lengkap, tapi **tidak** dihitung sebagai request user.

| Tanggal | Item | Sumber |
|---------|------|--------|
| 2026-05-27 | `.superpowers` masuk `.gitignore` | `70494e9` |
| 2026-06-12/14 | Update script deploy produksi | `246271c`, `3fff377`, `4242fc8` |
| 2026-07-22 | Perintah artisan import produk Excel + bersihkan data dummy | `f556581` |
| 2026-07-24 | Perbaikan deploy script & Laravel Reverb produksi | `011c492`, `a65b219` |
| 2026-08-02 | Backup + health monitoring + activity log + notifikasi | `371c7ee` |
| 2026-08-03 | Perbaikan upgrade Activitylog v5 (500/CSP) | `b438adf`, `676be8e` |
| 2026-08-04 | Upgrade base image FrankenPHP + storage debugbar | `ae3f637`, `c485a95` |
| 2026-08-09 | Konsolidasi seeder permission/role & admin | `08b0fc9` |
| 2026-08-19 | Perbaikan CSP pagination & DatePicker | `6c6b204`, `719071a` |
| 2026-08-26 | Seeder karyawan produksi | `f169621` |
| 2026-09-01 | Timeout ProcessImport + health check/restart queue worker | `6d2c7a2`, `7ef4f0c`, `00e7321` |
| 2026-09-04 | Konfigurasi backup DB + MySQL client/dump | `d151ee1`, `77e046a`, `6cc6e36`, `a17d233` |
| 2026-09-09 | Refactor format Cycle Index + test penomoran harian | `5ff11a8`, `6ac9221` |
| 2026-09-16 | Healthcheck endpoint + Docker healthcheck | `488f38a`, `456c11d` |
| 2026-09-16 | Data purge termasuk cycle completed & shopping shipped | `50bf98a` |
| 2026-09-17 | Optimasi memori + `ExportException` | `ff9bd7e` |
| 2026-09-17 | Backup health check + config | `03b5b42` |
| 2026-09-17 | Deploy lock + nginx buffer/large header | `27d472c`, `2e1137a`, `8e3b2b5`, `f59b1e7`, `ede33f2` |
| 2026-09-18 | Manual book import + update dokumentasi | `add5d4e` |

---

## 7. Cara Menjaga Log Ini

1. Setiap permintaan baru dari user (chat, UAT, meeting) → tambah satu baris.
2. Tentukan kategori dengan **§1 Kriteria**; kalau ragu, catat dua kandidat di kolom catatan.
3. Isi **Sumber**: nomor issue/spec/commit. Kalau belum ada spec, buat spec di
   `docs/superpowers/specs/` dulu (pola penamaan `YYYY-MM-DD-nama-design.md`).
4. Fitur baru/modul baru → **Major**. Perbaikan tampilan/teks/istilah → **Minor**.
5. Saran di luar kesepakatan awal → **Enhancement**, jangan langsung dikerjakan
   tanpa persetujuan PIC.
