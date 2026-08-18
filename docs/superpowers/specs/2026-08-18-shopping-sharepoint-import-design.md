# Desain Import Shopping dari Excel SharePoint

- **Tanggal:** 2026-08-18
- **Status:** Disetujui user (3 bagian desain)
- **Pendekatan terpilih:** A — ImportModal + ShoppingImporter (pola CycleImporter)
- **Sumber data:** file Excel SharePoint yang di-download manual lalu di-upload (tanpa integrasi auth SharePoint)

## Latar Belakang

User menerima file Excel dari Microsoft SharePoint berisi rencana pengiriman: baris per part dengan kolom `ID, Frame Number, PartNumber, PartName, Confirmed, Quantity, Modify Date, Modified`. Baris dengan Frame Number sama membentuk SATU Shopping; part number mengacu ke frame tersebut. Modul Shopping saat ini belum punya fitur import.

## Bagian 1 — Backend: ShoppingImporter

**File baru** `app/Services/ImportExport/Imports/ShoppingImporter.php`, mengikuti pola `CycleImporter` (grouping hirarkis):

- `modelType()` → `Shopping::class`; `uniqueKey()` → `frame_number`
- `rules()`: `frame_number` required string max 100; `part_number` required string max 100; `quantity` required integer min 1; `confirmed` nullable string; `modify_date` nullable date
- `templateHeadings()` → `['Frame Number', 'Part Number', 'Quantity', 'Confirmed', 'Modify Date']` (template download otomatis dari `BaseImporter`)
- Constructor: `__construct(private int $shoppingLocationId)` — lokasi dari form import
- `transformRow()`:
  - `Confirmed` bernilai eksplisit FALSE/0 → `RowTransformException` (baris gagal, import lanjut); **kosong/tidak dipetakan → dianggap TRUE** (diimpor)
  - Part number dicari di `products.part_number` via `resolveForeignKey()` — tidak ada → `RowTransformException`
  - `modify_date` diparse eksplisit dengan `Carbon::createFromFormat('d/m/Y H:i:s', ...)` (format file SharePoint, hindari ambiguitas m/d vs d/m); kosong/gagal parse → tanggal hari ini
- State `currentFrameNumber` / `currentShopping`:
  - Frame baru di file → cari Shopping dengan `frame_number` sama di DB: tidak ada → buat header baru (status draft, lokasi dari constructor, shopping_date dari baris); ada & draft → **merge** (pakai yang ada); ada & shipped/completed → `RowTransformException` "Frame sudah dikirim — tidak bisa digabung"
  - Item dibuat dengan `rack_id` = `default_rack_id` produk (nullable)
  - Part yang sudah ada di shopping tujuan (merge) → baris di-skip tanpa duplikat
- `insertRow()` menangani pembuatan header saat frame berganti (pola CycleImporter)

**Controller & route:**

- `ShoppingController` memakai trait `HasImportExport`; `importer()` membaca `shopping_location_id` dari request; `import()` & `importPreview()` di-override untuk menambahkan validasi `shopping_location_id => required|exists:shopping_locations,id`
- Routes baru dengan middleware `create shoppings`, **didaftarkan SEBELUM route parameterized `shoppings/{shopping}`** (pelajaran bug 404 suppliers):
  - `POST shoppings/import/preview` → `shoppings.import.preview`
  - `POST shoppings/import` → `shoppings.import`
  - `GET shoppings/import-template` → `shoppings.import-template`

## Bagian 2 — Frontend: ImportModal & Index Shopping

**Ekstensi `ImportModal.tsx`** (bersifat opsional, tidak mengubah perilaku modul lain):
- Prop baru `extraNode?: React.ReactNode` — dirender di step upload di atas dropzone.
- Prop baru `extraParams?: () => Record<string, string>` — nilainya ditambahkan ke FormData pada POST preview DAN import.
- Jika kedua prop tidak dipakai → perilaku identik seperti sekarang.

**`Shopping/Index.tsx`:**
- Tombol **Import** di baris aksi (permission `create shoppings`), membuka ImportModal dengan:
  - `extraNode`: SearchableSelect **Lokasi Tujuan** (opsi dari prop baru `shoppingLocations`)
  - `extraParams`: `() => ({ shopping_location_id: locationId })`
  - `fields`: Frame Number, Part Number, Quantity, Confirmed, Modify Date
  - `templateUrl`: `route('shoppings.import-template')`
- Modal tidak bisa mulai import sebelum lokasi dipilih (state lokal; controller tetap memvalidasi).

**`ShoppingController::index()`** menambahkan `shoppingLocations` ke props (pola yang sama dengan create/edit).

## Bagian 3 — Aturan Data, Testing & Verifikasi

**Rekap aturan import:**
1. Baris dikelompokkan per Frame Number → satu Shopping per frame.
2. Baris dengan `Confirmed = FALSE/0` eksplisit → baris gagal (import lanjut); kosong/tidak dipetakan → diimpor.
3. PartNumber harus ada di master produk; tidak ada → baris gagal, import lanjut, detail di riwayat import.
4. `Modify Date` → `shopping_date` (kosong → hari ini).
5. Frame sudah ada: draft → merge item (skip part duplikat); shipped/completed → grup gagal dengan pesan error.
6. Lokasi Tujuan = satu pilihan di form import untuk seluruh file.

**Testing (TDD, file baru `tests/Feature/ImportExport/ShoppingImportExportTest.php`, pola meniru `SupplierImportExportTest`):**
- Preview file valid → 200 + headers benar
- Import 2 frame × beberapa part → 2 Shopping + item sesuai (grouping)
- `Confirmed = FALSE` → baris di-skip
- Part tak dikenal → baris gagal, import tetap selesai
- Merge ke frame draft → item bertambah tanpa duplikat part
- Merge ke frame shipped → grup ditolak
- Validasi `shopping_location_id` wajib

**Verifikasi:** `php artisan test` (file baru) + `npm run build` + tes manual dengan sample data yang diberikan user.

**File yang tersentuh:**
- Baru: `app/Services/ImportExport/Imports/ShoppingImporter.php`, `tests/Feature/ImportExport/ShoppingImportExportTest.php`
- Ubah: `app/Http/Controllers/ShoppingController.php`, `routes/web.php`, `resources/js/Components/ImportExport/ImportModal.tsx`, `resources/js/Pages/Transactions/Shopping/Index.tsx`

## Non-Goals

- Tidak ada integrasi langsung ke SharePoint (auth/Graph API).
- Tidak mengubah alur import modul lain.
- Tidak menyentuh alur scan/manual Shopping yang sudah ada.
