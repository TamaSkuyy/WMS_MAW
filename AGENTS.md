# AGENTS.md — Warehouse Management System (WMS MAW)

Panduan singkat untuk agen coding di repo ini. **Baca ini dulu** — file ini dimuat
otomatis oleh harness, jadi jaga tetap ringkas. Detail panjang taruh di `docs/`.

## Ringkasan

- Laravel 13 (PHP `^8.3`, runtime 8.4) + MySQL 8.
- Frontend: Inertia 2 + React 19 + Tailwind 4 (tema Tailadmin), build pakai Vite.
- Domain WMS: master data (produk/rak/supplier/user), **Cycle** (penerimaan per
  gelombang), Shopping, Stock Opname, Delivery Monitor, laporan/dashboard.
- Bahasa UI: **Indonesia**. Otorisasi: Spatie Permission (mis. `create cycles`,
  `receive cycles`, `stock opname`, `import cycles`).
- File besar: `routes/web.php` ±560 baris, controller per modul (total ±5.100
  baris). **Grep nama method, jangan baca file controller utuh.**

## Aturan domain penting (jangan dilanggar)

1. **`cycle_number` = nomor gelombang HARI ITU per supplier** (reset tiap
   tanggal). Unik di `(supplier_id, delivery_date, cycle_number)` — migrasi
   `2026_09_08_000001_make_cycle_numbers_daily_per_supplier.php`.
   - Jangan pakai `max(cycle_number)` lintas tanggal.
   - Penomoran resmi: `CycleController::nextCycleNumberFor()` (manual/terima
     cepat), `DataOrderImportService::allocateCycleNumber()` dan
     `CycleImporter::allocateNumber()` (import, prefer nomor gelombang file).
2. **Cycle** = satu penerimaan/rencana supplier; status `draft → receiving →
   completed`. Stok hanya berubah saat receive (event `StockChanged`).
3. **Import Data Order** (file lebar TAM, sheet `EMAIL`: `Part Number`,
   `Supplier`, blok `CYCLE 1..16`) → `App\Services\DataOrder\DataOrderImportService`.
   Ada deteksi file identik (notes `Import Data Order — ref XXXXXXXX`) dan mode
   `replace`/`append`; hanya draft hasil import yang boleh dihapus.
4. **Import umum** (`App\Services\ImportExport`): template flat + `column_mapping`
   dari UI. `BaseImporter::normalizeRowValues()` menormalkan tanggal Excel
   serial/teks → `Y-m-d`; `ProcessImport` membuang baris kosong; `CycleImporter`
   melewati qty `0`, menerima supplier berupa kode **atau** nama, dan mengelompok
   per (supplier × tanggal × nomor file).
5. **Stock Opname** (`StockOpnameController`): header wajib `Part No`,
   `Qty Opname` (opsional `RAK`); normalisasi header = `strtolower` **dulu**, baru
   buang non-alphanumerik.
6. **Pagination**: index memakai trait `HasPagination` (`?per_page=10|25|50|100`);
   komponen `Pagination` mengurus per-page + lompat halaman via `usePage().url`.
7. **Hapus massal transaksi** (`shoppings/bulk-delete`, `cycles/bulk-delete`) hanya
   superadmin & semua status; stok dikoreksi lewat trait `AdjustsStock` (dijepit 0,
   `stock_shortage` dilaporkan).
8. **Shopping punya 3 alur — jangan hapus salah satu** (pusat/TAM bisa mengubah
   urutan kerja kapan saja):
   - **2 langkah**: (1) input header `shoppings/headers/create` (line + frame
     number, banyak baris sekaligus, `headerStore`), (2) import barang
     `shoppings/import-items` → `ShoppingItemImporter` (`requireExistingFrame`,
     frame tak dikenal = baris error kecuali opsi `auto_create_frame`).
   - **Import gabungan** lama (`shoppings/import` → `ShoppingImporter`) tetap ada.
   - **Import file header** (`shoppings/import-headers` → `ShoppingHeaderImporter`)
     backend siap, tombol UI disembunyikan (`SHOW_HEADER_IMPORT` di `Headers.tsx`).
   - **Kirim massal** (`shoppings/bulk-ship`, permission `ship shoppings`): mode
     `all: true` = kirim SELURUH draft sekali klik (tugas Leader setelah import,
     tombol "🚀 Kirim Semua" di banner). Kesiapan stok dihitung dulu oleh
     `App\Services\Shopping\BulkShipPlanner` (`bulk-ship/preview` →
     `ShoppingController::bulkShipPreview()`): simulasi berurutan urut id,
     jadi stok yang dipakai bersama antar frame tidak "kelihatan cukup" dua kali.
     `only_ready` (default = `all`) melewati frame yang stoknya kurang; frame itu
     tetap draft & dilaporkan alasannya. Batas `BulkShipPlanner::MAX_APPLY` (500)
     per permintaan + guard 200 detik → sisa dilaporkan sebagai `remaining`.
     Mode `ids: [...]` (pilih/scan frame) tetap ada. Balasan JSON hanya kalau
     `Accept: application/json`; kalau tidak, tetap redirect+flash (kompatibel
     dengan form/script lama & test lama).
   - Catatan **Model Kendaraan + Suffix** di form Shopping = combobox OPSIONAL yang
     bisa dicari (`Components/SearchableInput.tsx`): pilihannya dari master
     `vehicle_models` + nilai yang pernah diinput, dihitung oleh
     `ShoppingController::vehicleModelOptions()/vehicleSuffixOptions()`, tapi tetap
     boleh diketik baru. Menyaring daftar produk sekaligus tersimpan di
     `shoppings.vehicle_model_label` + `vehicle_suffix`, tampil di Detail. Kolom
     Model/Suffix **per item** di Detail & Reports/Export diturunkan dari
     `product.vehicleModel` (bukan data header).
9. **Payload index harus tetap kecil**: jangan kirim daftar draft/large list utuh
   ke Inertia (dulu penyebab 502 setelah import). Pakai endpoint pencarian seperti
   `shoppings/draft-frames` (`?search=&frame=&limit=`).
10. **Import = job queue**. `QUEUE_CONNECTION` tidak boleh `sync` (deploy script
    menolaknya). `ProcessImport` membatasi error tersimpan (`MAX_STORED_ERRORS`),
    dan kegagalan notifikasi tidak boleh menggagalkan import.
11. Batas runtime PHP/nginx di `Dockerfile` (`zz-wms.ini`) & `docker/nginx/default.conf`
    (`client_max_body_size 12M`) — perubahan infra butuh `--rebuild`.
12. **Health check** (`AppServiceProvider::backupsCheck()`): `BackupsCheck` wajib
    `onDisk('backup-db')` + `locatedAt(config('backup.backup.name'))` — tanpa itu
    `File::glob('')` → `The file "" does not exist` tiap 10 menit. Disk backup
    di-resolve saat boot, jadi `BACKUP_DB_PATH` (default `/backups`) dipakai
    dev/test; `backup:run` berjalan di container **scheduler** yang ikut mount
    `./backups/db`.
13. **Jangan muat koleksi tak terbatas di request** (penyebab OOM
    `Allowed memory size ... exhausted` di `BelongsTo.php`): summary/agregat
    laporan dihitung di SQL (`ReportController::*FilteredQuery()`); export CSV
    di-stream per halaman (`BaseExporter`), xlsx/pdf dibatasi
    (`ExportManager::MAX_ROWS_IN_MEMORY` → `ExportException` + flash error);
    daftar panjang pakai pagination/`limit`. Test penjaga:
    `tests/Feature/MemoryBudgetTest.php`.

14. **Toggle fitur (on/off)**: definisi di `config/features.php`, nilai efektif
    di tabel `settings` (`feature.<key>`) lewat `App\Support\Features`, diatur
    superadmin di `/settings` (menu Setup > Pengaturan, permission
    `manage settings`). Flag dibagikan ke frontend sebagai shared prop
    `features.<key>` — halaman membacanya via `usePage().props.features`.
    Toggle pertama: `shopping_vehicle_model_column` (kolom Model & Suffix di form
    Shopping Create/Edit, default ON).

15. **Stok = satu baris per bucket (produk × rak); `rack_id` NULL = RELAY**
    (barang diterima tapi belum masuk rak). Jangan pernah membuat baris stok
    kedua untuk bucket yang sama: sejak migrasi
    `2026_09_18_000001_add_null_safe_unique_index_to_stocks` ada index unik
    NULL-safe `(product_id, IFNULL(rack_id,0))` → insert duplikat DITOLAK MySQL.
    Perbaikan data stok dilakukan lewat CLI, bukan tinker manual:
    `stocks:audit` (read-only: duplikat, verdict baris RELAY PALSU/SAH/PERIKSA,
    riwayat opname, selisih vs buku besar — `App\Services\Stock\StockLedger`),
    `stocks:merge-duplicates`, `stocks:fix-phantom-relay` (baris RELAY palsu
    hasil opname tanpa kolom RAK), `stocks:set-quantity`
    (`--qty`/`--move-to`/`--from-ledger`, dry-run default, wajib `--reason` saat
    `--apply`). Klasifikasi PALSU/SAH/PERIKSA ada di
    `StockRepairService::relayBuckets()` — **hanya PALSU yang boleh dihapus
    otomatis** (qty relay persis dijelaskan satu opname "buat baris baru" &
    tidak ada penerimaan ke RELAY); RELAY yang SAH = overflow asli. Buku besar
    MENGHITUNG opname sebagai kebenaran, jadi baris RELAY palsu hasil opname
    tidak terlihat sebagai selisih di bagian 4 audit — pakai bagian 2/3.
    Penyesuaian CLI dicatat sebagai baris **stock opname** `FIX-…`
    (`StockRepairService::recordOpname()`) supaya konsisten dengan buku besar &
    muncul di Riwayat Opname. Buku besar = Σ `cycle_items.received_quantity` −
    Σ shopping terkirim + Σ diff opname (koreksi cycle/shopping sudah termasuk di
    dua suku pertama; `receive_logs` tidak dipakai karena baru ada sejak
    2026-07-29). Test penjaga: `tests/Feature/StockRepairTest.php`.

16. **Inventori Stok (`StockController::index`) — pencarian lengkap, jangan
    dibalikin ke `like` sederhana.** Query yang dikenali: `search` (multi-kata
    dipisah spasi = DAN; mencari part number, nama, deskripsi, supplier, model
    kendaraan, kode rak, zona, plus kata `relay` → `whereNull('rack_id')`),
    `rack_id`, `zone`, `supplier_id`, `status` (`rack|relay|low|zero|available`),
    `sort` (`qty_desc|qty_asc|part_asc|name_asc|rack_asc|updated_desc`),
    `product_id` (tombol "📦 Lihat Stok" di Detail Produk), `per_page`
    (default 25). Ringkasan (`summary`) dihitung **di SQL** untuk seluruh hasil
    filter, bukan dari koleksi (aturan 13). `Total Masuk` = Σ
    `cycle_items.received_quantity` (> 0), `Total Keluar` = Σ
    `shopping_items.quantity` untuk `StockLedger::OUT_STATUSES`. Test penjaga:
    `tests/Feature/StockIndexSearchTest.php`.

## Struktur

| Path | Isi |
| --- | --- |
| `routes/web.php` | Semua route web, dikelompokkan per permission Spatie |
| `app/Http/Controllers/` | Controller per modul (Cycle, Shopping, StockOpname, DeliveryMonitor, …) |
| `app/Services/DataOrder/` | Import file Data Order lebar → cycle draft |
| `app/Services/ImportExport/` | Sistem import/export generik (`Base/`, `Imports/`, `Jobs/ProcessImport.php`, `Support/RawFileImport.php`) |
| `app/Services/{Shopping,Stock,StockOpname,DeliveryMonitor,DataReset}/` | Logika domain terkait (`BulkShipPlanner`, `StockLedger`, `StockRepairService`, …) |
| `resources/js/Pages/Transactions/` | Halaman Inertia (Cycles, Shopping, StockOpname, Stocks) |
| `resources/js/Components/` | Komponen bersama (`ImportExport/`, `Cycles/`, Tailadmin) |
| `docs/` | Manual & spec; sebagian historis — verifikasi ke kode |
| `tests/Feature/` | Spesifikasi perilaku (pakai `RefreshDatabase`) |

## Perintah

```bash
# Test — JALANKAN PER FILE, jangan seluruh suite (lambat, migrasi MySQL nyata)
php artisan test tests/Feature/DataOrderImportTest.php
php artisan test --filter=NamaTest

php -l app/path/File.php            # cek sintaks cepat
npx tsc --noEmit -p tsconfig.json   # typecheck TS (hanya warning baseUrl lama)
npm run dev                         # Vite dev
npm run build                       # build produksi → public/build

php artisan migrate                 # dev DB: wms_maw
./dev-local.sh --help               # orkestrasi dev lokal (docker/infra)
```

Test DB: MySQL `127.0.0.1:3308` / `wms_maw_testing` (lihat `phpunit.xml`).

## Jebakan lingkungan (hemat waktu & token)

- Beberapa test **sudah gagal sejak awal** dan bukan regresi: 2 test `receive`
  di `CycleControllerTest` dan test HTTP di `RackImportExportTest` (403/404 /
  permission). Cek `git stash` dulu sebelum menuduh perubahan sendiri.
- Kelas `ImportLog` ada dua: `App\Models\ImportLog` hanya extends
  `App\Services\ImportExport\Models\ImportLog`; **di test importer, import dari
  `App\Services\ImportExport\Models\ImportLog`** agar tipe factory cocok.
- `givePermissionTo()` Spatie butuh permission-nya ada dulu:
  `Permission::findOrCreate('create cycles')`.
- Test kelas dengan `RefreshDatabase` makan ±15 detik+ per file — jalankan
  seperlunya.
- `docs/` banyak catatan historis yang bisa usang; kode & test = sumber kebenaran.
  Kalau mengubah perilaku yang didokumentasikan, update dokumennya sekalian.

## Konvensi UI/Backend

- Halaman Inertia: `usePage().props.auth.user.permissions` untuk cek izin tombol;
  `route()` (Ziggy) untuk URL; error backend di-parse dari JSON.
- Request non-Inertia (`fetch`) di komponen: sertakan `X-CSRF-TOKEN` dari
  `meta[name="csrf-token"]`, `Accept: application/json`.
- Validasi & pesan error: bahasa Indonesia, ditampilkan di UI.
- Jangan menambah server/docker baru untuk verifikasi; cukup `php artisan test`
  dan typecheck.

## Dokumen terpilih

- `docs/manual-import.md` — **manual book semua fitur import** (Data Order TAM,
  Barang Shopping alur 2 langkah, Produk & master data, Cycle, Stock Opname):
  format kolom per modul, arti angka hasil import, pesan error + solusi, checklist.
  Kalau mengubah importer/template/opsi import, **update dokumen ini sekalian**.
- `docs/delivery-monitor.md` — konsep slot C1-C6 vs nomor cycle.
- `docs/panduan-transaksi.md` — alur Quick Receive, Cycle, Data Order import.
- `docs/superpowers/specs/` — spec desain per fitur (arsip keputusan).
