# Master Data Import/Export — Design Spec

Date: 2026-07-08
Status: Approved

## Purpose

Add Import (Excel/CSV) and Export (Excel/CSV/PDF) to all 9 Master Data entities: Suppliers, Products, Racks, Model Kendaraan (VehicleModel), Kategori Produk (ProductCategory), Jabatan (JobPosition), Lokasi Kerja (WorkLocation), Departemen (Department), Karyawan (Employee).

The project already has a working Import/Export pipeline built for the Users page (`app/Services/ImportExport/**`, `resources/js/Components/ImportExport/**`). This feature generalizes that pipeline to cover Master Data instead of duplicating it per entity.

## Current State (reference implementation: Users)

- `BaseExporter` (abstract) — subclasses implement `headings()`, `exportQuery()`, `mapRow()`. `download()` dispatches to Xlsx/Csv/Pdf. No chunking (full `get()` into memory) — acceptable for Master Data table sizes.
- `ExportManager::download()` — thin orchestrator, throws if query is empty.
- `BaseImporter` (abstract) — subclasses implement `modelType()`, `uniqueKey()`, `rules()`, `chunkSize()`. `preview()` reads first sheet + samples for the column-mapping UI. `buildConfig()` persists the uploaded file and builds `ImportConfig`.
- `ImportManager` — `preview()`, `start()` (dispatches `ProcessImport` job), `status()`.
- `ProcessImport` (queued job) — reads the persisted file, chunks rows, per row: `mapRow()` → validate → dedupe check → `insertRow()` (`$modelClass::create($data)`). Tracks `processed_rows`/`skipped_rows`/`errors` on `ImportLog`. Currently **no hook for foreign-key resolution, no hook for injecting fixed fields (e.g. `created_by`), and dedupe only supports a single `uniqueKey` column**.
- `ImportLog` model / `import_logs` table — generic across entities via `model_type`, no schema change needed.
- Frontend: `ImportExportToolbar`, `ImportButton`, `ExportButton`, `ImportProgress` are already generic and reusable as-is. `ImportModal` has `SYSTEM_FIELDS` and template-download links **hard-coded to Users** — needs to become prop-driven.
- `UserController` currently owns `importPreview`/`import`/`export`/`importStatus` directly — no shared base/trait yet.

## Backend Architecture

### `BaseImporter` extensions (all default to no-op / backward compatible with `UserImporter`)

- `transformRow(array $mapped): array` — called after `mapRow()`, before validation. Resolves human-readable relation values into foreign key IDs and injects any fixed fields. Throws `RowTransformException` (new, under `app/Services/ImportExport/Exceptions/`) when a referenced name can't be resolved and the field is required; caught by `ProcessImport` and recorded as a row-level error (same shape as a validation failure), row skipped, import continues.
- `fixedFields(int $userId): array` — merged into the row before validation/insert. Default `[]`. Example: `EmployeeImporter::fixedFields()` returns `['created_by' => $userId, 'updated_by' => $userId]`.
- `uniqueKey(): string|array` — widened to accept an array of columns for composite-uniqueness dedupe (e.g. `VehicleModelImporter::uniqueKey() => ['name', 'brand']`).
- `resolveForeignKey(string $modelClass, string $lookupColumn, ?string $value): int` — protected helper for `transformRow()` implementations; throws `RowTransformException` if `$value` is empty (when required) or no matching row is found.
- `insertRow(array $data)` — moved from a private method on `ProcessImport` to a **protected, overridable** method on `BaseImporter` (default: `$this->modelType()::create($data)`), so entity-specific importers can override it for side effects beyond a plain create (needed by `SupplierImporter`, see below).
- `templateHeadings(): array` — default derives from `array_keys(rules())` title-cased; overridable when the template label should differ from the internal field key.

### Dedupe semantics

- `isDuplicate()` (moved alongside `insertRow` to `BaseImporter`, protected/overridable) supports both a single column and an array of columns (AND-matched) for `uniqueKey()`.
- If the value(s) for `uniqueKey()` are null/empty, the row is **never** treated as a duplicate (inserted normally) — matches DB `nullable unique` semantics, needed for Employee's nullable `nik`.

### Processing order in `ProcessImport::handle()` (per row)

`mapRow()` → `transformRow()` (FK resolve + fixed fields; may throw `RowTransformException` → logged as row error, row skipped) → `Validator` against `rules()` (validated against **post-transform** data, so FK rules are plain `exists:...,id`) → `isDuplicate()` (skip + count if true) → `insertRow()`.

### Template download (replaces static per-entity example files)

New generic endpoint per entity: `GET {entity}/import-template?format=xlsx|csv`. Builds a file containing only the header row, from `templateHeadings()`. No static files under `public/examples/` to maintain per entity/format.

### Shared controller trait

New `App\Http\Controllers\Concerns\HasImportExport` trait, applied to all 9 Master Data controllers. Each controller declares:
```php
protected function importer(): BaseImporter { return new XxxImporter(); }
protected function exporter(array $filters = []): BaseExporter { return new XxxExporter($filters); }
```
The trait provides `importPreview()`, `import()`, `export()`, `importTemplate()`, implemented the same way `UserController` does it today. `importStatus()` (keyed by the generic `import-status/{importLog}` route) moves off `UserController` onto a small shared controller (or the trait, applied once on a base) since it's already entity-agnostic (ownership checked via `ImportLog.user_id`), not duplicated 9 times.

### Routes

Per entity, added immediately after each `Route::resource(...)` line:
```php
Route::post('{entity}/import/preview', ...)->name('{entity}.import.preview');
Route::post('{entity}/import', ...)->name('{entity}.import');
Route::get('{entity}/export', ...)->name('{entity}.export');
Route::get('{entity}/import-template', ...)->name('{entity}.import-template');
```
All under the existing `Route::middleware('auth')` group — **no additional role/permission guard**, consistent with Master Data CRUD today (which has no granular permission checks either, only the blanket `auth` middleware). This intentionally differs from `UserController::export()`'s admin-only guard.

## Per-Entity Field Plan

`*` = required (matches existing create-form validation).

| Entity | Import template columns | `uniqueKey()` | FK resolution | `fixedFields()` |
|---|---|---|---|---|
| Rack | `code`*, `zone`* | `code` | – | – |
| Kategori Produk (ProductCategory) | `name`*, `description` | `name` | – | – |
| Jabatan (JobPosition) | `name`*, `level` | `name` | – | – |
| Lokasi Kerja (WorkLocation) | `name`* | `name` | – | – |
| Departemen (Department) | `name`* | `name` | – | – |
| Model Kendaraan (VehicleModel) | `name`*, `brand`*, `suffix` | `['name', 'brand']` | – | – |
| Supplier | `name`*, `contact_person`, `email`*, `phone`, `street`*, `city`*, `state`*, `postal_code`*, `country`* | `name` | – | – (`insertRow()` overridden to also create the related `SupplierAddress` with `address_type = 'primary'`, in the same flow) |
| Product | `part_number`*, `name`*, `brand`* (lookup), `model_kendaraan`* (lookup), `supplier`* (lookup by name), `kategori`* (lookup by name), `unit`*, `description`, `base_price`, `is_active`, `default_rack` (lookup by code, optional) | `part_number` | `brand`+`model_kendaraan` → `vehicle_model_id` (matched together to avoid ambiguity, mirrors the `(name, brand)` composite key); `supplier` name → `supplier_id`; `kategori` name → `category_id`; `default_rack` code → `default_rack_id` (optional) | – |
| Karyawan (Employee) | `name`*, `nik`, `job_position` (lookup), `work_location` (lookup), `department` (lookup), `phone`, `email`, `status`* (`Aktif`/`Nonaktif`) | `nik` (null-safe) | `job_position`/`work_location`/`department` names → respective IDs, all optional (null if blank/not found — **not** a hard error since these are optional relations) | `created_by`, `updated_by` = importing user's ID |

Notes:
- Employee's `user_id` (link to a login account) is **intentionally excluded** from import — too sensitive to resolve automatically from free text; stays a manual edit-form action.
- Product's Model Kendaraan uses two separate template columns (`brand`, `model_kendaraan`) rather than one combined column, to line up with the `(name, brand)` composite uniqueness and avoid ambiguous matches.
- For Employee, an unresolved optional relation name is treated as "leave null" rather than a `RowTransformException` (required-field lookups throw; optional-field lookups that fail to match just leave the FK null and continue).

## Frontend

- `ImportModal.tsx`: `SYSTEM_FIELDS` constant replaced with a `fields: {key, label, required}[]` prop (per the table above). Auto-mapping logic unchanged, just fed from the prop. Template download link becomes a `templateUrl` prop pointing at `{entity}.import-template`.
- `ImportExportToolbar`, `ImportButton`, `ExportButton`, `ImportProgress`: reused unmodified.
- Each of the 9 `resources/js/Pages/Master/{Entity}/Index.tsx` pages: `ImportExportToolbar` placed in the existing toolbar row next to the "Tambah {Entity}" button (not a separate wrapper div like `Users/Index.tsx`, to match the existing Master Data layout convention). `ImportModal` rendered once per page with that entity's `fields`/`templateUrl`/`importUrl`/`previewUrl`. `onComplete` does `window.location.reload()`, matching the Users pattern.

## Error Handling

- Row-level failures (validation, unresolved required FK via `RowTransformException`, duplicates) are recorded on `ImportLog.errors` with the existing `{row, field, message}` shape; the job continues past them. Surfaced via the unmodified `ImportProgress.tsx`.
- Export on an empty result set throws `RuntimeException('No data to export.')` from `ExportManager` — existing behavior, unchanged (Master Data filters are simple enough that empty results should be rare).
- No additional auth/permission guard beyond `auth` middleware (see Routes section).

## Testing

- One PHPUnit feature test file per entity (class-based `extends TestCase`, `RefreshDatabase`, matching the project's existing convention — not Pest syntax).
- Minimum coverage per entity: xlsx export succeeds, pdf export succeeds (regression check for the `BaseExporter`/`ExportManager` return-type bug fixed during the Transaction Report feature), valid row is imported, duplicate row is skipped, row with an unresolvable required relation name is recorded as an error (not a job crash).
- Extra coverage for Supplier (address row is created alongside the supplier) and Employee (`created_by`/`updated_by` injected, all three optional relations resolved or left null correctly).
- `npm run build` checked clean after the `ImportModal` and 9 Index page changes.

## Out of Scope

- Upsert-on-duplicate (decided: skip + report, matching existing Users behavior).
- Streaming/chunked export for very large tables (existing `BaseExporter::buildRows()` limitation, not addressed here — current Master Data table sizes don't need it).
- Any role/permission gating beyond `auth` (matches current Master Data CRUD).
- Linking Employee to a User account via import.
- Column-selection or filtered export UI (the unused `ExportConfig->columns`/`->filters` fields stay unused, as they are for Users export today).
