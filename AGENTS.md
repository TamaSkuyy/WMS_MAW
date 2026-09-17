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

## Struktur

| Path | Isi |
| --- | --- |
| `routes/web.php` | Semua route web, dikelompokkan per permission Spatie |
| `app/Http/Controllers/` | Controller per modul (Cycle, Shopping, StockOpname, DeliveryMonitor, …) |
| `app/Services/DataOrder/` | Import file Data Order lebar → cycle draft |
| `app/Services/ImportExport/` | Sistem import/export generik (`Base/`, `Imports/`, `Jobs/ProcessImport.php`, `Support/RawFileImport.php`) |
| `app/Services/{StockOpname,DeliveryMonitor,DataReset}/` | Logika domain terkait |
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

- `docs/delivery-monitor.md` — konsep slot C1-C6 vs nomor cycle.
- `docs/panduan-transaksi.md` — alur Quick Receive, Cycle, Data Order import.
- `docs/superpowers/specs/` — spec desain per fitur (arsip keputusan).
