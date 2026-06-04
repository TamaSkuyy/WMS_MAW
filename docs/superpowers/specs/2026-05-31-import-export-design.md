# Import/Export System Design

## Overview

Build a reusable import/export foundation for the MAW Warehouse Management System, with User import/export as the concrete reference implementation.

- **Formats**: Excel (.xlsx) + CSV for import and export; PDF for export only
- **Architecture**: Laravel Jobs + Queues (non-blocking background processing)
- **Import strategy**: Insert-only — skip rows that match an existing record by unique key
- **Libraries**: `maatwebsite/excel` (Excel/CSV), `barryvdh/laravel-dompdf` (PDF)

## Backend Structure

```
app/Services/ImportExport/
├── Contracts/
│   ├── Importable.php          — rules(), uniqueKey(), chunkSize()
│   └── Exportable.php          — headings(), exportQuery(), mapRow()
├── Base/
│   ├── BaseImporter.php        — reads Excel/CSV, validates, chunks, dispatches
│   └── BaseExporter.php        — generates Excel/CSV/PDF via format config
├── Managers/
│   ├── ImportManager.php       — validate file → create job → track status
│   └── ExportManager.php       — build query → format → stream/download
├── Jobs/
│   └── ProcessImport.php       — reads chunk, maps columns, inserts, tracks progress
├── DTOs/
│   ├── ImportConfig.php        — format, column mapping, validation rules, batch size
│   └── ExportConfig.php        — format, selected columns, filters, filename
├── Enums/
│   ├── ImportFormat.php        — Xlsx, Csv
│   ├── ExportFormat.php        — Xlsx, Csv, Pdf
│   └── ImportStatus.php        — pending, processing, completed, failed
├── Models/
│   └── ImportLog.php           — tracks job status, row counts, errors, file ref
├── Exports/
│   └── UserExporter.php        — reference implementation
└── Imports/
    └── UserImporter.php        — reference implementation
```

### Contracts

**Importable** — each importable model implements:
- `rules(): array` — Laravel validation rules per column
- `uniqueKey(): string` — column used to detect duplicates (e.g. `email` for Users)
- `chunkSize(): int` — rows per queue chunk (default 500)

**Exportable** — each exportable model implements:
- `headings(): array` — column header labels
- `exportQuery(): Builder` — base query for export data
- `mapRow($model): array` — transforms a model instance to a flat row array

### Base Classes

**BaseImporter**: Chunks file using `maatwebsite/excel`'s `WithChunkReading`, validates each row against the model's `rules()`, inserts valid rows (skipping duplicates per `uniqueKey()`), collects per-row errors into `ImportLog.errors`.

**BaseExporter**: Accepts `ExportConfig`, uses strategy pattern per format — `maatwebsite/excel`'s `FromQuery` for Excel/CSV, `barryvdh/laravel-dompdf` with a Blade view for PDF. Small datasets (≤ 2000 rows) stream directly to browser; larger go to queue.

### Import Flow

1. **POST /users/import/preview** — `ImportManager::preview()` reads first 3 rows, returns detected headers + sample data for column mapping
2. **POST /users/import** — `ImportManager::start()` creates `ImportLog` (status=pending), dispatches `ProcessImport` job, returns `import_log_id`
3. **GET /import-status/{importLog}** — poll for progress; also pushed via Reverb notification on completion
4. **ProcessImport job**: reads file in chunks → validates → maps → inserts (skip duplicates) → updates ImportLog

### Export Flow

1. **GET /users/export?format=xlsx|csv|pdf** — `ExportManager::download()` streams small exports directly, queues large ones
2. Large exports return a download link when the job completes

### Routes

| Method | Path | Purpose |
|--------|------|---------|
| POST | /users/import/preview | Preview uploaded file |
| POST | /users/import | Start import |
| GET | /import-status/{importLog} | Poll job progress |
| GET | /import-logs | Import history page |
| GET | /users/export?format=xlsx | Download export |
| POST | /export | Queue large export |

## Database

### import_logs

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint PK | |
| user_id | FK users | Who initiated |
| model_type | string | Target model class |
| file_name | string | Original filename |
| file_format | string | xlsx / csv |
| status | enum | pending, processing, completed, failed |
| column_mapping | json | {email: "A", name: "B"} |
| total_rows | int | Rows in file |
| processed_rows | int | Rows processed so far |
| skipped_rows | int | Duplicates skipped |
| errors | json nullable | [{row, field, message}] |
| created_at / updated_at | timestamps | |

## Error Handling

| Scenario | Handling |
|----------|----------|
| Invalid file format | Rejected at preview (422) |
| Missing required columns | Preview flags unmapped fields; user must fix before import |
| Row-level validation failure | Captured per-row in `ImportLog.errors[]`; valid rows still process |
| Duplicate record | Skipped silently; counted in `skipped_rows` |
| Queue timeout/crash | ImportLog stays `processing`; retry button dispatches from last chunk |
| Empty file | Caught at preview — "No data rows found" |

## Frontend Components

```
resources/js/Components/ImportExport/
├── ImportButton.tsx         — triggers file input + modal
├── ImportModal.tsx          — 2-step wizard: upload → column mapping
├── ImportProgress.tsx       — real-time progress bar (polling + Reverb)
├── ExportButton.tsx         — dropdown: Excel / CSV / PDF
├── ExportModal.tsx          — select columns, apply filters, filename
└── ImportExportToolbar.tsx  — wrapper with import + export buttons for table pages
```

### ImportModal (2-step wizard)

**Step 1 — Upload**: Drag-and-drop zone, file format validation (xlsx/csv, max 10MB), displays detected filename and row count preview.

**Step 2 — Map Columns**: Shows system fields (from `Importable::rules()`) on the left, dropdowns for file columns on the right. Auto-matches by header name similarity. User must map all required fields before starting import.

### ImportProgress

Embedded progress bar: shows `processed_rows / total_rows`, current status, error count, duplicate count. Polls `/import-status/{id}` every 3 seconds, switches to Reverb push notification for completion event.

### ExportButton

Dropdown with three options: Excel, CSV, PDF. For small datasets (< 2000 rows), triggers immediate download. For large datasets, shows ExportModal for column selection, then queues.

## Testing

### Feature Tests (PHPUnit + RefreshDatabase)

- ImportManager::preview() parses headers and sample rows
- ImportManager::start() dispatches ProcessImport job
- ProcessImport handles valid rows, skips duplicates, collects errors
- ProcessImport fails gracefully on malformed file
- ExportManager generates xlsx/csv/pdf with correct columns
- ImportLog tracks state transitions correctly

### Unit Tests

- BaseImporter column mapping logic
- ImportConfig / ExportConfig DTO validation
- ImportFormat / ExportFormat / ImportStatus enum values

## Dependencies

- `maatwebsite/laravel-excel` — Excel and CSV import/export
- `barryvdh/laravel-dompdf` — PDF export
- Laravel Queue (database driver, already migrated via `jobs` table)
- Laravel Reverb (already configured for real-time notifications)

## Out of Scope

- Inline editing of imported data before commit
- Import/export scheduling (cron-based recurring imports)
- Third-party API integrations (e.g., direct Shopify/WooCommerce sync)
- Custom export templates (beyond column selection)
