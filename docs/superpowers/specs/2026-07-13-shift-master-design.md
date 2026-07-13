# Master Shift — Design Spec

Date: 2026-07-13

## Background

Requirement: "bantu tambahkan master shift yang digunakan untuk master karyawan" — a new master-data entity for work shifts, linked to Employee so each employee can be assigned a fixed shift.

The project already has an established pattern for simple master-data entities (`JobPosition`, `WorkLocation`, `Department`) and a generic import/export service layer (`app/Services/ImportExport/`, `HasImportExport` controller trait). Shift follows that exact pattern rather than introducing anything new architecturally.

## Scope

- New `shifts` master-data table + full CRUD (index/create/edit/delete, no `show` page) with import/export.
- `employees.shift_id` nullable FK — one fixed shift per employee, changeable at any time (not a per-date schedule).
- Overnight shifts are supported: `end_time` may be earlier than `start_time` (e.g. 22:00–06:00), no ordering validation between the two.
- Sidebar menu entry "Shift" under "Master Data", shipped via an idempotent data migration (not only the dev seeder), matching the pattern established in `2026_07_08_000001_add_report_transaction_menu.php` — this is the only mechanism that reliably reaches production, since `deploy-production.sh` runs `migrate --force` but never `db:seed`.

Out of scope: per-date/per-week shift scheduling (a single `shift_id` column is enough per requirements gathering), break-time columns, multi-shift-per-employee assignment, permission-gating beyond the existing `auth` middleware (matches how other master-data pages are protected today).

## 1. Database schema

New migration `database/migrations/{ts}_create_shifts_table.php`:

```php
Schema::create('shifts', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('code', 10)->unique();
    $table->time('start_time');
    $table->time('end_time');
    $table->string('status')->default('Aktif');
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index('status');
});
```

`end_time < start_time` is a valid, expected state (overnight shift) and must not be rejected by validation or a DB constraint.

New migration `database/migrations/{ts}_add_shift_id_to_employees_table.php`:

```php
Schema::table('employees', function (Blueprint $table) {
    $table->foreignId('shift_id')->nullable()->after('department_id')
        ->constrained('shifts')->nullOnDelete();
});
```

## 2. Backend

- `app/Models/Shift.php` — `fillable = ['name', 'code', 'start_time', 'end_time', 'status', 'created_by', 'updated_by']`; `hasMany(Employee::class)`; `active()` scope mirroring `Employee`'s.
- `app/Models/Employee.php` — add `shift_id` to `fillable`; add `belongsTo(Shift::class)`.
- `app/Http/Controllers/ShiftController.php` — index/create/store/edit/update/destroy via Inertia, uses `HasImportExport` trait (`importer()`, `exporter()`, `exportFileName()`), sets `created_by`/`updated_by` from `auth()->id()`. Validation:
  - `name`: required, string, max:100, unique
  - `code`: required, string, max:10, unique
  - `start_time`, `end_time`: required, `date_format:H:i` (no cross-field ordering rule)
  - `status`: required, in:Aktif,Nonaktif
- `app/Http/Controllers/EmployeeController.php` — `create()`/`edit()` also pass `Shift::orderBy('name')->get()`; `store()`/`update()` accept and persist `shift_id` (nullable, `exists:shifts,id`).
- `app/Services/ImportExport/Imports/ShiftImporter.php` extends `BaseImporter implements Importable` — `uniqueKey()` on `code`, `rules()` mirrors controller validation.
- `app/Services/ImportExport/Exports/ShiftExporter.php` extends `BaseExporter` — columns: Nama, Kode, Jam Mulai, Jam Selesai, Status.
- Routes in `routes/web.php` (inside existing `auth` middleware group), following the `JobPosition` block exactly:

```php
Route::post('shifts/import/preview', [ShiftController::class, 'importPreview'])->name('shifts.import.preview');
Route::post('shifts/import', [ShiftController::class, 'import'])->name('shifts.import');
Route::get('shifts/export', [ShiftController::class, 'export'])->name('shifts.export');
Route::get('shifts/import-template', [ShiftController::class, 'importTemplate'])->name('shifts.import-template');
Route::resource('shifts', ShiftController::class)->except(['show']);
```

## 3. Menu

New idempotent data migration `database/migrations/{ts}_add_shift_menu.php`, following `2026_07_08_000001_add_report_transaction_menu.php`:

```php
public function up(): void
{
    if (DB::table('menus')->where('name', 'Shift')->exists()) {
        return;
    }

    $masterDataId = DB::table('menus')->where('name', 'Master Data')->value('id');

    DB::table('menus')->insert([
        'name' => 'Shift',
        'icon' => 'CalenderIcon',
        'path' => '/shifts',
        'parent_id' => $masterDataId,
        'sort_order' => 9, // after Departemen (8)
        'group' => 'main',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
```

`CalenderIcon` is used because it is the closest icon actually registered in `resources/js/Tailadmin/components/IconMapper.tsx`'s `iconsMap` — no clock icon is registered there, and using an unregistered name (e.g. `ClockIcon`) would silently fall back to the default `BoxCubeIcon`.

Also add the matching entry to `database/seeders/MenuSeeder.php` (same fields) so fresh dev installs via `db:seed` stay in sync with what the migration ships.

## 4. Frontend

- `resources/js/Pages/Master/Shifts/Index.tsx` — list + search, columns: Nama, Kode, Jam Mulai, Jam Selesai, Status, Aksi; wired with `ImportExportToolbar`/`ImportModal` using `route('shifts.import')`, `.import.preview`, `.export`, `.import-template` (mirrors `Employees/Index.tsx`).
- `resources/js/Pages/Master/Shifts/Create.tsx` / `Edit.tsx` — `useForm` with `name`, `code`, `start_time` (`<input type="time">`), `end_time` (`<input type="time">`), `status` (`Select`), submitting to `route('shifts.store')` / `route('shifts.update', id)` (mirrors `JobPositions/Create.tsx`).
- `resources/js/Pages/Master/Employees/Create.tsx` / `Edit.tsx` — add a `Select` "Shift" (optional) alongside the existing Jabatan/Lokasi Kerja/Departemen selects, sending `shift_id`.
- `resources/js/Pages/Master/Employees/Index.tsx` — add a "Shift" column showing the assigned shift's name, or `-` when unset.
- No changes needed to `AppSidebar.tsx` — menu rendering is fully data-driven from the `menus` table.

## 5. Testing

- `tests/Feature/ShiftControllerTest.php` — CRUD happy path; validation failures for duplicate `name`/`code`, missing/invalid time format; overnight shift (`end_time < start_time`) is accepted; delete.
- `tests/Feature/ImportExport/ShiftImportExportTest.php` — import preview, successful import, duplicate detection by `code`, export xlsx/csv/pdf, template download (mirrors `SupplierImportExportTest.php`).
- `tests/Feature/EmployeeControllerTest.php` — extend to cover `shift_id` accepted as nullable on create/update, and `employee->shift` relation resolves correctly.
- Menu migration — assert running it twice does not duplicate the "Shift" row (idempotency), and that it inserts under the correct `parent_id`.
