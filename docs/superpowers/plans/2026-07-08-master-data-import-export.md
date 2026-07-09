# Master Data Import/Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Import (Excel/CSV) and Export (Excel/CSV/PDF) to all 9 Master Data entities (Suppliers, Products, Racks, Model Kendaraan, Kategori Produk, Jabatan, Lokasi Kerja, Departemen, Karyawan) by generalizing the existing Users import/export pipeline instead of duplicating it.

**Architecture:** Extend `BaseImporter` with three hooks (`transformRow`, `fixedFields`, widened `uniqueKey`) so per-entity `Importer` classes can resolve human-readable relation names into foreign key IDs and inject audit fields. Introduce a shared `HasImportExport` controller trait so each of the 9 controllers gets `importPreview`/`import`/`export`/`importTemplate` for free by declaring only `importer()`/`exporter()`/`exportFileName()`. Parameterize the existing `ImportModal` React component (`fields`/`templateUrl`/`title` props) so it's reusable across entities instead of hard-coded to Users.

**Tech Stack:** Laravel 11, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, Inertia.js + React (TypeScript), PHPUnit (class-based, not Pest).

## Global Constraints

- Duplicate rows on import are always **skipped and reported** — never upserted (matches existing Users import behavior).
- Import/export routes for the 9 Master Data entities use only the existing `auth` middleware — **no additional role/permission guard**. `UserController::export()` keeps its existing admin-only guard unchanged (that is pre-existing behavior outside this feature's scope, not to be removed).
- `phpunit.xml` sets `QUEUE_CONNECTION=sync`, so `ProcessImport::dispatch()` runs synchronously inside feature tests — no `Queue::fake()`/`Bus::fake()` needed, `import_logs` rows are fully processed by the time an HTTP test assertion runs.
- All new PHPUnit tests are class-based (`extends Tests\TestCase`, `use RefreshDatabase`), matching the existing convention — not Pest syntax.
- Employee's `user_id` (login account link) is excluded from import entirely — stays a manual edit-form action.
- `npm run build` must stay clean after every frontend-touching task.

---

## Task 1: Foundation — BaseImporter hooks, RowTransformException, ProcessImport rewrite

**Files:**
- Create: `app/Services/ImportExport/Exceptions/RowTransformException.php`
- Modify: `app/Services/ImportExport/Contracts/Importable.php`
- Modify: `app/Services/ImportExport/DTOs/ImportConfig.php`
- Modify: `app/Services/ImportExport/Base/BaseImporter.php`
- Modify: `app/Services/ImportExport/Jobs/ProcessImport.php`
- Modify: `tests/Unit/ImportExport/ImportConfigTest.php`
- Modify: `tests/Feature/ImportExport/ProcessImportTest.php`
- Test (new): `tests/Feature/ImportExport/BaseImporterHooksTest.php`

**Interfaces:**
- Produces: `BaseImporter::transformRow(array $mapped): array` (default passthrough), `BaseImporter::fixedFields(int $userId): array` (default `[]`), `BaseImporter::uniqueKey(): string|array`, `BaseImporter::isDuplicate(array $data): bool` (public, supports composite keys, null-safe), `BaseImporter::insertRow(array $data): void` (public, overridable), `BaseImporter::templateHeadings(): array` (default derives from `rules()` keys via `Str::headline`), `BaseImporter::resolveForeignKey(string $modelClass, string $lookupColumn, ?string $value, bool $required = true): ?int` (protected helper for `transformRow()` overrides, throws `RowTransformException`), `ImportConfig::$importerClass` (FQCN of the concrete `BaseImporter` subclass).
- Consumes: nothing from other tasks (this is the foundation).

- [ ] **Step 1: Write the failing test for the new hooks**

Create `tests/Feature/ImportExport/BaseImporterHooksTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Rack;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exceptions\RowTransformException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StubCompositeImporter extends BaseImporter
{
    public function modelType(): string
    {
        return Rack::class;
    }

    public function uniqueKey(): string|array
    {
        return ['code', 'zone'];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'zone' => ['required', 'string'],
        ];
    }
}

class StubForeignKeyImporter extends BaseImporter
{
    public function modelType(): string
    {
        return Rack::class;
    }

    public function uniqueKey(): string|array
    {
        return 'code';
    }

    public function rules(): array
    {
        return ['code' => ['required']];
    }

    public function transformRow(array $mapped): array
    {
        $mapped['zone_id'] = $this->resolveForeignKey(Rack::class, 'code', $mapped['zone_lookup'] ?? null);
        unset($mapped['zone_lookup']);

        return $mapped;
    }
}

class BaseImporterHooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_transform_row_is_passthrough(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertEquals(
            ['code' => 'A1', 'zone' => 'Z1'],
            $importer->transformRow(['code' => 'A1', 'zone' => 'Z1'])
        );
    }

    public function test_default_fixed_fields_is_empty(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertEquals([], $importer->fixedFields(1));
    }

    public function test_composite_unique_key_detects_duplicate_only_on_full_match(): void
    {
        Rack::create(['code' => 'A1', 'zone' => 'Zone A']);

        $importer = new StubCompositeImporter();

        $this->assertTrue($importer->isDuplicate(['code' => 'A1', 'zone' => 'Zone A']));
        $this->assertFalse($importer->isDuplicate(['code' => 'A1', 'zone' => 'Zone B']));
    }

    public function test_null_unique_key_value_is_never_a_duplicate(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertFalse($importer->isDuplicate(['code' => null, 'zone' => 'Zone A']));
    }

    public function test_resolve_foreign_key_returns_matching_id(): void
    {
        $rack = Rack::create(['code' => 'B2', 'zone' => 'Zone B']);

        $importer = new StubForeignKeyImporter();
        $result = $importer->transformRow(['zone_lookup' => 'B2']);

        $this->assertEquals($rack->id, $result['zone_id']);
    }

    public function test_resolve_foreign_key_throws_when_not_found(): void
    {
        $importer = new StubForeignKeyImporter();

        $this->expectException(RowTransformException::class);
        $importer->transformRow(['zone_lookup' => 'DOES-NOT-EXIST']);
    }

    public function test_default_template_headings_derive_from_rules(): void
    {
        $importer = new StubCompositeImporter();

        $this->assertEquals(['Code', 'Zone'], $importer->templateHeadings());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/BaseImporterHooksTest.php`
Expected: FAIL — `transformRow`/`fixedFields`/`resolveForeignKey`/`templateHeadings` don't exist yet, `uniqueKey(): string` return type doesn't accept arrays, `isDuplicate`/`insertRow` aren't public methods on `BaseImporter`.

- [ ] **Step 3: Create RowTransformException**

Create `app/Services/ImportExport/Exceptions/RowTransformException.php`:

```php
<?php

namespace App\Services\ImportExport\Exceptions;

class RowTransformException extends \RuntimeException
{
}
```

- [ ] **Step 4: Widen the Importable contract**

Modify `app/Services/ImportExport/Contracts/Importable.php` — replace the whole file:

```php
<?php

namespace App\Services\ImportExport\Contracts;

interface Importable
{
    /** @return array<string, array<int, string>> Laravel validation rules keyed by column */
    public function rules(): array;

    /** Column(s) used to detect duplicate rows (e.g. 'email', or ['name', 'brand'] for composite uniqueness) */
    public function uniqueKey(): string|array;

    /** Rows to process per queue chunk */
    public function chunkSize(): int;
}
```

- [ ] **Step 5: Add importerClass to ImportConfig**

Modify `app/Services/ImportExport/DTOs/ImportConfig.php` — replace the whole file:

```php
<?php

namespace App\Services\ImportExport\DTOs;

use App\Services\ImportExport\Enums\ImportFormat;

readonly class ImportConfig
{
    public function __construct(
        public ImportFormat $format,
        public string $filePath,
        public string $modelType,
        public array $columnMapping,
        public array $validationRules,
        public string|array $uniqueKey,
        public string $importerClass,
        public int $chunkSize = 500,
    ) {}
}
```

- [ ] **Step 6: Rewrite BaseImporter with the new hooks**

Modify `app/Services/ImportExport/Base/BaseImporter.php` — replace the whole file:

```php
<?php

namespace App\Services\ImportExport\Base;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Exceptions\RowTransformException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

abstract class BaseImporter
{
    abstract public function modelType(): string;

    abstract public function uniqueKey(): string|array;

    abstract public function rules(): array;

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Resolve human-readable relation values and inject any fixed data before validation.
     * Override to resolve foreign keys via resolveForeignKey(), or to add extra keys.
     * Throw RowTransformException to mark the row as failed without aborting the import.
     */
    public function transformRow(array $mapped): array
    {
        return $mapped;
    }

    /** Fixed field values merged into every row before transformRow() runs (e.g. audit columns). */
    public function fixedFields(int $userId): array
    {
        return [];
    }

    public function isDuplicate(array $data): bool
    {
        $keys = (array) $this->uniqueKey();

        foreach ($keys as $key) {
            if (empty($data[$key] ?? null)) {
                return false;
            }
        }

        $modelClass = $this->modelType();
        $query = $modelClass::query();
        foreach ($keys as $key) {
            $query->where($key, $data[$key]);
        }

        return $query->exists();
    }

    public function insertRow(array $data): void
    {
        $modelClass = $this->modelType();
        $modelClass::create($data);
    }

    /** @return array<string> Column labels for the downloadable import template */
    public function templateHeadings(): array
    {
        return array_map(
            fn (string $key) => Str::headline($key),
            array_keys($this->rules())
        );
    }

    protected function resolveForeignKey(string $modelClass, string $lookupColumn, ?string $value, bool $required = true): ?int
    {
        $value = is_string($value) ? trim($value) : $value;

        if (empty($value)) {
            if ($required) {
                throw new RowTransformException(
                    "Kolom relasi wajib diisi (lookup {$lookupColumn} pada ".class_basename($modelClass).")."
                );
            }

            return null;
        }

        $id = $modelClass::where($lookupColumn, $value)->value('id');

        if ($id === null) {
            if ($required) {
                throw new RowTransformException(
                    class_basename($modelClass)." dengan {$lookupColumn} \"{$value}\" tidak ditemukan."
                );
            }

            return null;
        }

        return $id;
    }

    public function preview(UploadedFile $file): array
    {
        $path = $file->store('imports/temp');

        $rows = Excel::toCollection(null, Storage::path($path))->first();

        $headers = $rows && $rows->isNotEmpty()
            ? array_keys($rows->first()->toArray())
            : [];
        $samples = $rows
            ? $rows->take(3)->map(fn ($r) => $r->toArray())->values()->toArray()
            : [];
        $totalRows = $rows ? $rows->count() : 0;

        Storage::delete($path);

        return [
            'headers' => $headers,
            'samples' => $samples,
            'total_rows' => $totalRows,
        ];
    }

    public function buildConfig(
        UploadedFile $file,
        array $columnMapping,
        ?ImportFormat $format = null,
    ): ImportConfig {
        $path = $file->storeAs(
            'imports/' . now()->format('Y-m-d'),
            uniqid() . '.' . $file->getClientOriginalExtension()
        );

        $detectedFormat = $format ?? ($file->getClientOriginalExtension() === 'csv'
            ? ImportFormat::Csv
            : ImportFormat::Xlsx);

        return new ImportConfig(
            format: $detectedFormat,
            filePath: Storage::path($path),
            modelType: $this->modelType(),
            columnMapping: $columnMapping,
            validationRules: $this->rules(),
            uniqueKey: $this->uniqueKey(),
            importerClass: static::class,
            chunkSize: $this->chunkSize(),
        );
    }
}
```

- [ ] **Step 7: Rewrite ProcessImport to use the importer instance**

Modify `app/Services/ImportExport/Jobs/ProcessImport.php` — replace the whole file:

```php
<?php

namespace App\Services\ImportExport\Jobs;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Exceptions\RowTransformException;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ImportConfig $config,
        private readonly int $importLogId,
    ) {}

    public function handle(): void
    {
        $importLog = ImportLog::findOrFail($this->importLogId);

        $importLog->update([
            'status' => ImportStatus::Processing->value,
            'column_mapping' => $this->config->columnMapping,
        ]);

        $importerClass = $this->config->importerClass;
        $importer = new $importerClass();
        $fixedFields = $importer->fixedFields($importLog->user_id);

        $errors = [];
        $processed = 0;
        $skipped = 0;

        $rows = Excel::toCollection(null, $this->config->filePath)->first();

        if ($rows->isEmpty()) {
            $importLog->update([
                'status' => ImportStatus::Completed->value,
                'total_rows' => 0,
            ]);
            return;
        }

        $headers = array_keys($rows->first()->toArray());

        $importLog->update(['total_rows' => $rows->count()]);

        $chunks = $rows->chunk($this->config->chunkSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $rowIndex => $row) {
                $rowNumber = ($chunkIndex * $this->config->chunkSize) + $rowIndex + 1;
                $rowArray = $row->toArray();
                $mapped = array_merge($this->mapRow($rowArray, $headers), $fixedFields);

                try {
                    $transformed = $importer->transformRow($mapped);
                } catch (RowTransformException $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'system',
                        'message' => $e->getMessage(),
                    ];
                    continue;
                }

                $validator = Validator::make($transformed, $this->config->validationRules);

                if ($validator->fails()) {
                    foreach ($validator->errors()->toArray() as $field => $msgs) {
                        foreach ($msgs as $msg) {
                            $errors[] = [
                                'row' => $rowNumber,
                                'field' => $field,
                                'message' => $msg,
                            ];
                        }
                    }
                    continue;
                }

                if ($importer->isDuplicate($transformed)) {
                    $skipped++;
                    continue;
                }

                $importer->insertRow($transformed);
                $processed++;
            }
        }

        $importLog->update([
            'status' => ImportStatus::Completed->value,
            'processed_rows' => $processed,
            'skipped_rows' => $skipped,
            'errors' => $errors,
        ]);

        if ($importLog->user) {
            $importLog->user->notify(
                new \App\Notifications\ImportCompletedNotification($importLog)
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        ImportLog::where('id', $this->importLogId)->update([
            'status' => ImportStatus::Failed->value,
            'errors' => array_merge(
                ImportLog::find($this->importLogId)?->errors ?? [],
                [['row' => 0, 'field' => 'system', 'message' => $e->getMessage()]]
            ),
        ]);
    }

    private function mapRow(array $row, array $headers): array
    {
        $mapped = [];
        foreach ($this->config->columnMapping as $field => $fileColumn) {
            $index = array_search($fileColumn, $headers);
            if ($index !== false) {
                $mapped[$field] = $row[$fileColumn] ?? $row[$index] ?? null;
            }
        }
        return $mapped;
    }
}
```

- [ ] **Step 8: Run the new test to verify it passes**

Run: `php artisan test tests/Feature/ImportExport/BaseImporterHooksTest.php`
Expected: PASS (7 tests)

- [ ] **Step 9: Fix the existing regression tests broken by the DTO/signature change**

Modify `tests/Unit/ImportExport/ImportConfigTest.php` — replace the whole file:

```php
<?php

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Imports\UserImporter;
use Tests\TestCase;

class ImportConfigTest extends TestCase
{
    public function test_import_config_has_correct_defaults(): void
    {
        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: '/tmp/test.csv',
            modelType: 'App\Models\User',
            columnMapping: ['email' => 'A'],
            validationRules: ['email' => ['required']],
            uniqueKey: 'email',
            importerClass: UserImporter::class,
        );

        $this->assertEquals(ImportFormat::Csv, $config->format);
        $this->assertEquals('/tmp/test.csv', $config->filePath);
        $this->assertEquals(500, $config->chunkSize);
        $this->assertEquals('email', $config->uniqueKey);
        $this->assertEquals(UserImporter::class, $config->importerClass);
    }
}
```

Modify `tests/Feature/ImportExport/ProcessImportTest.php` — add the import and `importerClass` argument. First add the import right after the existing `use App\Services\ImportExport\Jobs\ProcessImport;` line:

```php
use App\Services\ImportExport\Jobs\ProcessImport;
use App\Services\ImportExport\Imports\UserImporter;
```

Then, in each of the three `new ImportConfig(...)` calls in that file, add `importerClass: UserImporter::class,` right after the `uniqueKey: 'email',` line (there are three identical `uniqueKey: 'email',` lines — add the new line after each of the three occurrences):

```php
            uniqueKey: 'email',
            importerClass: UserImporter::class,
```

- [ ] **Step 10: Run the full ImportExport test suite to verify no regressions**

Run: `php artisan test tests/Feature/ImportExport tests/Unit/ImportExport`
Expected: PASS — all tests in `ProcessImportTest`, `ImportManagerTest`, `ExportManagerTest`, `ImportConfigTest`, `BaseImporterHooksTest`, `EnumsTest` green.

- [ ] **Step 11: Commit**

```bash
git add app/Services/ImportExport/Exceptions/RowTransformException.php \
        app/Services/ImportExport/Contracts/Importable.php \
        app/Services/ImportExport/DTOs/ImportConfig.php \
        app/Services/ImportExport/Base/BaseImporter.php \
        app/Services/ImportExport/Jobs/ProcessImport.php \
        tests/Unit/ImportExport/ImportConfigTest.php \
        tests/Feature/ImportExport/ProcessImportTest.php \
        tests/Feature/ImportExport/BaseImporterHooksTest.php
git commit -m "feat: add transformRow/fixedFields/composite-uniqueKey hooks to BaseImporter"
```

---

## Task 2: HasImportExport trait, ImportStatusController, generic import-template, rewire UserController

**Files:**
- Create: `app/Http/Controllers/Concerns/HasImportExport.php`
- Create: `app/Http/Controllers/ImportStatusController.php`
- Modify: `app/Services/ImportExport/Base/BaseImporter.php` (add `downloadTemplate()`)
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `routes/web.php`
- Test (new): `tests/Feature/ImportExport/UserImportExportRegressionTest.php`

**Interfaces:**
- Consumes: `BaseImporter`/`BaseExporter` from Task 1 (unchanged surface except the new `downloadTemplate()` method added here).
- Produces: trait `App\Http\Controllers\Concerns\HasImportExport` with public methods `importPreview(Request)`, `import(Request)`, `export(Request)`, `importTemplate(Request)`, and protected `performExport(Request)`; abstract contract methods `importer(): BaseImporter`, `exporter(): BaseExporter`, `exportFileName(): string` that every consuming controller (Tasks 4-12) must implement. `ImportStatusController::show(ImportLog $importLog)` (moved from `UserController::importStatus`).

- [ ] **Step 1: Write the failing regression test**

Create `tests/Feature/ImportExport/UserImportExportRegressionTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserImportExportRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_still_requires_admin_role(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('users.export', ['format' => 'xlsx']));

        $response->assertForbidden();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('users.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_status_route_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $importLog = ImportLog::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger);
        $this->get(route('import.status', $importLog->id))->assertForbidden();

        $this->actingAs($owner);
        $this->get(route('import.status', $importLog->id))->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/UserImportExportRegressionTest.php`
Expected: FAIL — `users.import-template` route doesn't exist yet (`RouteNotFoundException`).

- [ ] **Step 3: Add downloadTemplate() to BaseImporter**

Modify `app/Services/ImportExport/Base/BaseImporter.php` — add these imports right after the existing `use Maatwebsite\Excel\Facades\Excel;` line:

```php
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
```

Then add these three methods right after `templateHeadings()` and before `resolveForeignKey()`:

```php
    public function downloadTemplate(ImportFormat $format): BinaryFileResponse|StreamedResponse
    {
        $headings = $this->templateHeadings();

        return match ($format) {
            ImportFormat::Xlsx => $this->downloadTemplateXlsx($headings),
            ImportFormat::Csv => $this->downloadTemplateCsv($headings),
        };
    }

    private function downloadTemplateXlsx(array $headings): BinaryFileResponse
    {
        $export = new class($headings) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $headings) {}
            public function array(): array { return []; }
            public function headings(): array { return $this->headings; }
        };

        return Excel::download($export, 'import-template.xlsx');
    }

    private function downloadTemplateCsv(array $headings): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="import-template.csv"');

        return $response;
    }
```

- [ ] **Step 4: Create the HasImportExport trait**

Create `app/Http/Controllers/Concerns/HasImportExport.php`:

```php
<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Managers\ExportManager;
use App\Services\ImportExport\Managers\ImportManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HasImportExport
{
    abstract protected function importer(): BaseImporter;

    abstract protected function exporter(): BaseExporter;

    abstract protected function exportFileName(): string;

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = app(ImportManager::class)->preview($this->importer(), $request->file('file'));

        return response()->json($result);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $importLog = app(ImportManager::class)->start(
            $this->importer(),
            $request->file('file'),
            $request->input('column_mapping'),
            Auth::id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function export(Request $request)
    {
        return $this->performExport($request);
    }

    protected function performExport(Request $request)
    {
        $format = ExportFormat::from($request->query('format', 'xlsx'));
        $exporter = $this->exporter();

        $config = new ExportConfig(
            format: $format,
            fileName: $this->exportFileName() . '-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: [],
            exportableClass: $exporter::class,
        );

        return app(ExportManager::class)->download($exporter, $config);
    }

    public function importTemplate(Request $request)
    {
        $format = ImportFormat::from($request->query('format', 'xlsx'));

        return $this->importer()->downloadTemplate($format);
    }
}
```

- [ ] **Step 5: Create ImportStatusController**

Create `app/Http/Controllers/ImportStatusController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Support\Facades\Auth;

class ImportStatusController extends Controller
{
    public function show(ImportLog $importLog)
    {
        if ($importLog->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'id' => $importLog->id,
            'status' => $importLog->status,
            'total_rows' => $importLog->total_rows,
            'processed_rows' => $importLog->processed_rows,
            'skipped_rows' => $importLog->skipped_rows,
            'errors' => $importLog->errors,
        ]);
    }
}
```

- [ ] **Step 6: Rewire UserController onto the trait**

Modify `app/Http/Controllers/UserController.php` — replace the imports block and class opening:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\User;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\UserExporter;
use App\Services\ImportExport\Imports\UserImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new UserImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new UserExporter();
    }

    protected function exportFileName(): string
    {
        return 'users-export';
    }

    public function index()
```

Then remove the four methods `importPreview`, `import`, `export`, `importStatus` entirely from the bottom of the file, replacing them with only this (which overrides the trait's `export()` to keep the admin-only guard):

```php
    public function export(Request $request)
    {
        if (!Auth::user()?->hasRole('admin')) {
            abort(403, 'Only administrators can export user data.');
        }

        return $this->performExport($request);
    }
}
```

- [ ] **Step 7: Update routes**

Modify `routes/web.php` — replace these two lines:

```php
    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::get('import-status/{importLog}', [UserController::class, 'importStatus'])->name('import.status');
```

with:

```php
    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::get('users/import-template', [UserController::class, 'importTemplate'])->name('users.import-template');
    Route::get('import-status/{importLog}', [App\Http\Controllers\ImportStatusController::class, 'show'])->name('import.status');
```

- [ ] **Step 8: Restart Octane if running**

New routes/controllers require a server restart to be picked up (no `--with-watch` in local dev). Ask the user to restart `dev-local.sh` before running HTTP feature tests if the app is served via Octane in this environment; PHPUnit itself doesn't need Octane running.

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/UserImportExportRegressionTest.php tests/Feature/ImportExport tests/Unit/ImportExport`
Expected: PASS — all tests green, including the pre-existing `ImportManagerTest`/`ExportManagerTest`/`ProcessImportTest` (proving the trait-based `UserController` didn't break existing behavior).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Concerns/HasImportExport.php \
        app/Http/Controllers/ImportStatusController.php \
        app/Services/ImportExport/Base/BaseImporter.php \
        app/Http/Controllers/UserController.php \
        routes/web.php \
        tests/Feature/ImportExport/UserImportExportRegressionTest.php
git commit -m "refactor: extract HasImportExport trait, move importStatus to its own controller"
```

---

## Task 3: Parameterize ImportModal.tsx (fields/templateUrl/title props)

**Files:**
- Modify: `resources/js/Components/ImportExport/ImportModal.tsx`
- Modify: `resources/js/Pages/Users/Index.tsx`

**Interfaces:**
- Consumes: nothing backend (uses the `users.import-template` route added in Task 2).
- Produces: `ImportModal` props `{ isOpen, onClose, onComplete?, importUrl, previewUrl, templateUrl, fields: {key,label,required}[], title }` — this exact prop shape is what every entity's Index.tsx page (Tasks 4-12) will pass.

- [ ] **Step 1: Replace ImportModal.tsx with the parameterized version**

Modify `resources/js/Components/ImportExport/ImportModal.tsx` — replace the whole file:

```tsx
import React, { useState, useRef } from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';
import ImportProgress from './ImportProgress';

export interface ImportField {
  key: string;
  label: string;
  required: boolean;
}

interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete?: () => void;
  importUrl: string;
  previewUrl: string;
  templateUrl: string;
  fields: ImportField[];
  title: string;
}

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
}

function extractError(text: string, status: number): string {
  try {
    const data = JSON.parse(text);
    if (data.message) return data.message;
    if (data.error) return data.error;
    if (data.errors) {
      const first = Object.values(data.errors).flat()[0];
      if (first) return String(first);
    }
  } catch {}

  if (/login|Log In|Sign In/i.test(text) && /<html/i.test(text)) {
    return 'Session expired. Please refresh the page and try again.';
  }

  const stripped = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  if (stripped.length > 0 && stripped.length < 500) return stripped;

  return `Server error (HTTP ${status})`;
}

export default function ImportModal({ isOpen, onClose, onComplete, importUrl, previewUrl, templateUrl, fields, title }: ImportModalProps) {
  const [step, setStep] = useState<'upload' | 'mapping' | 'importing'>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [headers, setHeaders] = useState<string[]>([]);
  const [totalRows, setTotalRows] = useState(0);
  const [columnMapping, setColumnMapping] = useState<Record<string, string>>({});
  const [importLogId, setImportLogId] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  if (!isOpen) return null;

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;
    setFile(f);
    setLoading(true);

    const formData = new FormData();
    formData.append('file', f);

    try {
      const res = await fetch(previewUrl, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const text = await res.text();

      if (!res.ok) throw new Error(extractError(text, res.status));

      let data: any;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error('Server returned an invalid response');
      }

      setHeaders(data.headers);
      setTotalRows(data.total_rows);

      const autoMapping: Record<string, string> = {};
      for (const field of fields) {
        const match = data.headers.find(
          (h: string) => typeof h === 'string' && h.toLowerCase().replace(/[^a-z]/g, '') === field.key.toLowerCase()
        );
        if (match) autoMapping[field.key] = match;
      }
      setColumnMapping(autoMapping);
      setStep('mapping');
    } catch (err: any) {
      alert(err.message || 'Failed to preview file');
    } finally {
      setLoading(false);
    }
  };

  const handleImport = async () => {
    if (!file) return;
    setLoading(true);

    const formData = new FormData();
    formData.append('file', file);
    Object.entries(columnMapping).forEach(([key, value]) => {
      formData.append(`column_mapping[${key}]`, value);
    });

    try {
      const res = await fetch(importUrl, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const text = await res.text();

      if (!res.ok) throw new Error(extractError(text, res.status));

      let data: any;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error('Server returned an invalid response');
      }

      setImportLogId(data.import_log_id);
      setStep('importing');
    } catch (err: any) {
      alert(err.message || 'Failed to start import');
    } finally {
      setLoading(false);
    }
  };

  const handleComplete = () => {
    onComplete?.();
    onClose();
    setStep('upload');
    setFile(null);
    setImportLogId(null);
  };

  return (
    <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">
              {step === 'upload' && `Import ${title}`}
              {step === 'mapping' && 'Map Columns'}
              {step === 'importing' && 'Importing...'}
            </h2>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              disabled={step === 'importing'}
            >
              ✕
            </button>
          </div>

          {step === 'upload' && (
            <div>
              <div
                className="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-brand-500 transition-colors"
                onClick={() => fileRef.current?.click()}
              >
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  Drop file here or click to browse
                </p>
                <p className="text-xs text-gray-400 mt-1">XLSX, CSV (max 10MB)</p>
              </div>
              <p className="text-xs text-gray-400 mt-2 text-center">
                Download template:{' '}
                <a
                  href={`${templateUrl}?format=csv`}
                  className="text-brand-500 hover:text-brand-600 hover:underline transition-colors"
                  download
                >
                  CSV
                </a>
                {' | '}
                <a
                  href={`${templateUrl}?format=xlsx`}
                  className="text-brand-500 hover:text-brand-600 hover:underline transition-colors"
                  download
                >
                  XLSX
                </a>
              </p>
              <input
                ref={fileRef}
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={handleFileChange}
                className="hidden"
              />
              {file && <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">Selected: {file.name}</p>}
              <div className="flex justify-end gap-3 mt-6">
                <Button variant="outline" onClick={onClose}>Cancel</Button>
              </div>
            </div>
          )}

          {step === 'mapping' && (
            <div>
              <p className="text-sm text-gray-500 mb-4">{totalRows} rows detected.</p>
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-700">
                    <th className="text-left py-2 text-xs font-medium text-gray-500 uppercase">System Field</th>
                    <th className="text-left py-2 text-xs font-medium text-gray-500 uppercase">File Column</th>
                  </tr>
                </thead>
                <tbody>
                  {fields.map((field) => (
                    <tr key={field.key} className="border-b border-gray-100 dark:border-gray-800">
                      <td className="py-2">
                        {field.label}
                        {field.required && <span className="text-red-500 ml-1">*</span>}
                      </td>
                      <td className="py-2">
                        <select
                          className="w-full px-3 py-1.5 border rounded-lg text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                          value={columnMapping[field.key] || ''}
                          onChange={(e) =>
                            setColumnMapping((prev) => ({ ...prev, [field.key]: e.target.value }))
                          }
                        >
                          <option value="">-- Select --</option>
                          {headers.map((h) => (
                            <option key={h} value={h}>{h}</option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="flex justify-end gap-3 mt-6">
                <Button variant="outline" onClick={() => setStep('upload')} disabled={loading}>Back</Button>
                <Button
                  onClick={handleImport}
                  disabled={loading || fields.some((f) => f.required && !columnMapping[f.key])}
                >
                  {loading ? 'Starting...' : 'Start Import'}
                </Button>
              </div>
            </div>
          )}

          {step === 'importing' && importLogId && (
            <div>
              <ImportProgress importLogId={importLogId} onComplete={handleComplete} />
              <div className="flex justify-end mt-6">
                <Button variant="outline" onClick={handleComplete}>Close</Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Update Users/Index.tsx to pass the new required props**

Modify `resources/js/Pages/Users/Index.tsx` — replace the `<ImportModal ... />` block:

```tsx
            <ImportModal
                isOpen={importModalOpen}
                onClose={() => setImportModalOpen(false)}
                onComplete={() => {
                    window.location.reload();
                }}
                importUrl={route('users.import')}
                previewUrl={route('users.import.preview')}
                templateUrl={route('users.import-template')}
                title="Users"
                fields={[
                    { key: 'name', label: 'Name', required: true },
                    { key: 'email', label: 'Email', required: true },
                    { key: 'password', label: 'Password', required: true },
                ]}
            />
```

- [ ] **Step 3: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors (in particular no missing-prop errors on `ImportModal` usage in `Users/Index.tsx`).

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/ImportExport/ImportModal.tsx resources/js/Pages/Users/Index.tsx
git commit -m "refactor: parameterize ImportModal fields/template/title instead of hard-coding to Users"
```

---

## Task 4: Rack import/export

**Files:**
- Create: `app/Services/ImportExport/Imports/RackImporter.php`
- Create: `app/Services/ImportExport/Exports/RackExporter.php`
- Modify: `app/Http/Controllers/RackController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/Racks/Index.tsx`
- Test (new): `tests/Feature/ImportExport/RackImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), parameterized `ImportModal` (Task 3).
- Produces: routes `racks.import.preview`, `racks.import`, `racks.export`, `racks.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/RackImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RackImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Rack::factory()->create(['code' => 'A1', 'zone' => 'Zone A']);

        $response = $this->get(route('racks.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Rack::factory()->create(['code' => 'A1', 'zone' => 'Zone A']);

        $response = $this->get(route('racks.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('racks.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'racks.csv',
            "Kode,Zona\nC1,Zone C\nC2,Zone C"
        );

        $response = $this->post(route('racks.import'), [
            'file' => $file,
            'column_mapping' => ['code' => 'Kode', 'zone' => 'Zona'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('racks', ['code' => 'C1', 'zone' => 'Zone C']);
        $this->assertDatabaseHas('racks', ['code' => 'C2', 'zone' => 'Zone C']);
    }

    public function test_import_skips_duplicate_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Rack::factory()->create(['code' => 'D1', 'zone' => 'Zone D']);

        $file = UploadedFile::fake()->createWithContent(
            'racks.csv',
            "Kode,Zona\nD1,Zone D Updated"
        );

        $response = $this->post(route('racks.import'), [
            'file' => $file,
            'column_mapping' => ['code' => 'Kode', 'zone' => 'Zona'],
        ]);

        $response->assertOk();
        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1, 'processed_rows' => 0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/RackImportExportTest.php`
Expected: FAIL — routes `racks.export`/`racks.import`/`racks.import-template` don't exist yet.

- [ ] **Step 3: Create RackImporter**

Create `app/Services/ImportExport/Imports/RackImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Rack;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class RackImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Rack::class;
    }

    public function uniqueKey(): string|array
    {
        return 'code';
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'zone' => ['required', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Kode', 'Zona'];
    }
}
```

- [ ] **Step 4: Create RackExporter**

Create `app/Services/ImportExport/Exports/RackExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Rack;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class RackExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Kode', 'Zona'];
    }

    public function exportQuery(): Builder
    {
        return Rack::query()->orderBy('zone')->orderBy('code');
    }

    public function mapRow($model): array
    {
        return [$model->code, $model->zone];
    }
}
```

- [ ] **Step 5: Wire RackController onto the trait**

Modify `app/Http/Controllers/RackController.php` — replace the top of the file:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Rack;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\RackExporter;
use App\Services\ImportExport\Imports\RackImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RackController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new RackImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new RackExporter();
    }

    protected function exportFileName(): string
    {
        return 'racks-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('racks', RackController::class);`, add:

```php
    Route::post('racks/import/preview', [RackController::class, 'importPreview'])->name('racks.import.preview');
    Route::post('racks/import', [RackController::class, 'import'])->name('racks.import');
    Route::get('racks/export', [RackController::class, 'export'])->name('racks.export');
    Route::get('racks/import-template', [RackController::class, 'importTemplate'])->name('racks.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/RackImportExportTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/Racks/Index.tsx` — replace the whole file:

```tsx
import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ racks, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus rak ini?')) {
            router.delete(route('racks.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Rak" />
            <PageBreadcrumb pageTitle="Rak" />
            <ComponentCard title="Daftar Rak">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('racks.create')}>
                        <Button>Tambah Rak</Button>
                    </Link>
                    <SearchInput
                        placeholder="Cari kode atau zona rak..."
                        routeName="racks.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('racks.import')}
                        previewUrl={route('racks.import.preview')}
                        exportUrl={route('racks.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('racks.import')}
                    previewUrl={route('racks.import.preview')}
                    templateUrl={route('racks.import-template')}
                    title="Rak"
                    fields={[
                        { key: 'code', label: 'Kode', required: true },
                        { key: 'zone', label: 'Zona', required: true },
                    ]}
                />
                {racks.data.length === 0 ? (
                    <EmptyState
                        icon="🗄️"
                        title="Belum ada rak"
                        message="Daftarkan lokasi rak penyimpanan."
                        actionLabel="Tambah Rak"
                        actionRoute={route('racks.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {racks.data.map((rack: any) => (
                                <tr key={rack.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono">{rack.code}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm">{rack.zone}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <TableActions
                                            viewRoute={route('racks.show', rack.id)}
                                            editRoute={route('racks.edit', rack.id)}
                                            onDelete={() => handleDelete(rack.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {racks.total > racks.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {racks.from || 0} sampai {racks.to || 0} dari {racks.total}</div>
                        <div className="flex gap-2">
                            {racks.prev_page_url ? <Link href={racks.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {racks.current_page} dari {racks.last_page}</span>
                            {racks.next_page_url ? <Link href={racks.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                                : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/RackImporter.php \
        app/Services/ImportExport/Exports/RackExporter.php \
        app/Http/Controllers/RackController.php \
        routes/web.php \
        resources/js/Pages/Master/Racks/Index.tsx \
        tests/Feature/ImportExport/RackImportExportTest.php
git commit -m "feat: add import/export to Rack master data"
```

---

## Task 5: Kategori Produk (ProductCategory) import/export

**Files:**
- Create: `app/Services/ImportExport/Imports/ProductCategoryImporter.php`
- Create: `app/Services/ImportExport/Exports/ProductCategoryExporter.php`
- Modify: `app/Http/Controllers/ProductCategoryController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/ProductCategories/Index.tsx`
- Test (new): `tests/Feature/ImportExport/ProductCategoryImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3).
- Produces: routes `product-categories.import.preview`, `product-categories.import`, `product-categories.export`, `product-categories.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/ProductCategoryImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductCategoryImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ProductCategory::factory()->create();

        $response = $this->get(route('product-categories.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ProductCategory::factory()->create();

        $response = $this->get(route('product-categories.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('product-categories.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'categories.csv',
            "Nama,Deskripsi\nBody Parts,Panel bodi kendaraan"
        );

        $response = $this->post(route('product-categories.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'description' => 'Deskripsi'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_categories', ['name' => 'Body Parts', 'description' => 'Panel bodi kendaraan']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ProductCategory::factory()->create(['name' => 'Engine Components']);

        $file = UploadedFile::fake()->createWithContent(
            'categories.csv',
            "Nama,Deskripsi\nEngine Components,Duplicate"
        );

        $response = $this->post(route('product-categories.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'description' => 'Deskripsi'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/ProductCategoryImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create ProductCategoryImporter**

Create `app/Services/ImportExport/Imports/ProductCategoryImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\ProductCategory;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class ProductCategoryImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return ProductCategory::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Deskripsi'];
    }
}
```

- [ ] **Step 4: Create ProductCategoryExporter**

Create `app/Services/ImportExport/Exports/ProductCategoryExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\ProductCategory;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Deskripsi'];
    }

    public function exportQuery(): Builder
    {
        return ProductCategory::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->description];
    }
}
```

- [ ] **Step 5: Wire ProductCategoryController onto the trait**

Modify `app/Http/Controllers/ProductCategoryController.php` — replace the top of the file:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\ProductCategory;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\ProductCategoryExporter;
use App\Services\ImportExport\Imports\ProductCategoryImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductCategoryController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new ProductCategoryImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new ProductCategoryExporter();
    }

    protected function exportFileName(): string
    {
        return 'product-categories-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('product-categories', ProductCategoryController::class)->except(['show']);`, add:

```php
    Route::post('product-categories/import/preview', [ProductCategoryController::class, 'importPreview'])->name('product-categories.import.preview');
    Route::post('product-categories/import', [ProductCategoryController::class, 'import'])->name('product-categories.import');
    Route::get('product-categories/export', [ProductCategoryController::class, 'export'])->name('product-categories.export');
    Route::get('product-categories/import-template', [ProductCategoryController::class, 'importTemplate'])->name('product-categories.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/ProductCategoryImportExportTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/ProductCategories/Index.tsx` following the exact same pattern as Task 4 Step 8 (Racks): add these two imports right after the existing `EmptyState` import:

```tsx
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';
```

Change `import React from 'react';` to `import React, { useState } from 'react';`.

Add `const [importModalOpen, setImportModalOpen] = useState(false);` as the first line inside the `Index` function body, before `const handleDelete = ...`.

Replace the toolbar div:

```tsx
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('product-categories.create')}><Button>Tambah Kategori</Button></Link>
                    <SearchInput
                        placeholder="Cari nama kategori..."
                        routeName="product-categories.index"
                        filters={filters}
                    />
                </div>
```

with:

```tsx
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('product-categories.create')}><Button>Tambah Kategori</Button></Link>
                    <SearchInput
                        placeholder="Cari nama kategori..."
                        routeName="product-categories.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('product-categories.import')}
                        previewUrl={route('product-categories.import.preview')}
                        exportUrl={route('product-categories.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('product-categories.import')}
                    previewUrl={route('product-categories.import.preview')}
                    templateUrl={route('product-categories.import-template')}
                    title="Kategori Produk"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'description', label: 'Deskripsi', required: false },
                    ]}
                />
```

Only this toolbar block and the two imports/state line change — leave the rest of the file (table, pagination) untouched.

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/ProductCategoryImporter.php \
        app/Services/ImportExport/Exports/ProductCategoryExporter.php \
        app/Http/Controllers/ProductCategoryController.php \
        routes/web.php \
        resources/js/Pages/Master/ProductCategories/Index.tsx \
        tests/Feature/ImportExport/ProductCategoryImportExportTest.php
git commit -m "feat: add import/export to Kategori Produk master data"
```

---

## Task 6: Jabatan (JobPosition) import/export

**Files:**
- Create: `app/Services/ImportExport/Imports/JobPositionImporter.php`
- Create: `app/Services/ImportExport/Exports/JobPositionExporter.php`
- Modify: `app/Http/Controllers/JobPositionController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/JobPositions/Index.tsx`
- Test (new): `tests/Feature/ImportExport/JobPositionImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3).
- Produces: routes `job-positions.import.preview`, `job-positions.import`, `job-positions.export`, `job-positions.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/JobPositionImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class JobPositionImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        JobPosition::factory()->create();

        $response = $this->get(route('job-positions.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        JobPosition::factory()->create();

        $response = $this->get(route('job-positions.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('job-positions.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'positions.csv',
            "Nama,Level\nStaff Gudang,Staff"
        );

        $response = $this->post(route('job-positions.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'level' => 'Level'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('job_positions', ['name' => 'Staff Gudang', 'level' => 'Staff']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        JobPosition::factory()->create(['name' => 'Manager Gudang']);

        $file = UploadedFile::fake()->createWithContent(
            'positions.csv',
            "Nama,Level\nManager Gudang,Manager"
        );

        $response = $this->post(route('job-positions.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'level' => 'Level'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/JobPositionImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create JobPositionImporter**

Create `app/Services/ImportExport/Imports/JobPositionImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\JobPosition;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class JobPositionImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return JobPosition::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Level'];
    }
}
```

- [ ] **Step 4: Create JobPositionExporter**

Create `app/Services/ImportExport/Exports/JobPositionExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\JobPosition;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class JobPositionExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Level'];
    }

    public function exportQuery(): Builder
    {
        return JobPosition::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->level];
    }
}
```

- [ ] **Step 5: Wire JobPositionController onto the trait**

Modify `app/Http/Controllers/JobPositionController.php` — replace the top of the file:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\JobPosition;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\JobPositionExporter;
use App\Services\ImportExport\Imports\JobPositionImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobPositionController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new JobPositionImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new JobPositionExporter();
    }

    protected function exportFileName(): string
    {
        return 'job-positions-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('job-positions', JobPositionController::class)->except(['show']);`, add:

```php
    Route::post('job-positions/import/preview', [JobPositionController::class, 'importPreview'])->name('job-positions.import.preview');
    Route::post('job-positions/import', [JobPositionController::class, 'import'])->name('job-positions.import');
    Route::get('job-positions/export', [JobPositionController::class, 'export'])->name('job-positions.export');
    Route::get('job-positions/import-template', [JobPositionController::class, 'importTemplate'])->name('job-positions.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/JobPositionImportExportTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/JobPositions/Index.tsx` — add these two imports right after the existing `EmptyState` import:

```tsx
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';
```

Change `import React from 'react';` to `import React, { useState } from 'react';`.

Add `const [importModalOpen, setImportModalOpen] = useState(false);` as the first line inside the `Index` function body, before `const handleDelete = ...`.

Replace the toolbar div:

```tsx
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('job-positions.create')}><Button>Tambah Jabatan</Button></Link>
                    <SearchInput
                        placeholder="Cari nama jabatan..."
                        routeName="job-positions.index"
                        filters={filters}
                    />
                </div>
```

with:

```tsx
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('job-positions.create')}><Button>Tambah Jabatan</Button></Link>
                    <SearchInput
                        placeholder="Cari nama jabatan..."
                        routeName="job-positions.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('job-positions.import')}
                        previewUrl={route('job-positions.import.preview')}
                        exportUrl={route('job-positions.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('job-positions.import')}
                    previewUrl={route('job-positions.import.preview')}
                    templateUrl={route('job-positions.import-template')}
                    title="Jabatan"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'level', label: 'Level', required: false },
                    ]}
                />
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/JobPositionImporter.php \
        app/Services/ImportExport/Exports/JobPositionExporter.php \
        app/Http/Controllers/JobPositionController.php \
        routes/web.php \
        resources/js/Pages/Master/JobPositions/Index.tsx \
        tests/Feature/ImportExport/JobPositionImportExportTest.php
git commit -m "feat: add import/export to Jabatan master data"
```

---

## Task 7: Lokasi Kerja (WorkLocation) import/export

**Files:**
- Create: `app/Services/ImportExport/Imports/WorkLocationImporter.php`
- Create: `app/Services/ImportExport/Exports/WorkLocationExporter.php`
- Modify: `app/Http/Controllers/WorkLocationController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/WorkLocations/Index.tsx`
- Test (new): `tests/Feature/ImportExport/WorkLocationImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3).
- Produces: routes `work-locations.import.preview`, `work-locations.import`, `work-locations.export`, `work-locations.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/WorkLocationImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WorkLocationImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        WorkLocation::factory()->create();

        $response = $this->get(route('work-locations.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        WorkLocation::factory()->create();

        $response = $this->get(route('work-locations.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('work-locations.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'locations.csv',
            "Nama\nGudang Cikarang"
        );

        $response = $this->post(route('work-locations.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('work_locations', ['name' => 'Gudang Cikarang']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        WorkLocation::factory()->create(['name' => 'Gudang Bekasi']);

        $file = UploadedFile::fake()->createWithContent(
            'locations.csv',
            "Nama\nGudang Bekasi"
        );

        $response = $this->post(route('work-locations.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/WorkLocationImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create WorkLocationImporter**

Create `app/Services/ImportExport/Imports/WorkLocationImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class WorkLocationImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return WorkLocation::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama'];
    }
}
```

- [ ] **Step 4: Create WorkLocationExporter**

Create `app/Services/ImportExport/Exports/WorkLocationExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class WorkLocationExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama'];
    }

    public function exportQuery(): Builder
    {
        return WorkLocation::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name];
    }
}
```

- [ ] **Step 5: Wire WorkLocationController onto the trait**

Modify `app/Http/Controllers/WorkLocationController.php` — replace the top of the file:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\WorkLocationExporter;
use App\Services\ImportExport\Imports\WorkLocationImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkLocationController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new WorkLocationImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new WorkLocationExporter();
    }

    protected function exportFileName(): string
    {
        return 'work-locations-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('work-locations', WorkLocationController::class)->except(['show']);`, add:

```php
    Route::post('work-locations/import/preview', [WorkLocationController::class, 'importPreview'])->name('work-locations.import.preview');
    Route::post('work-locations/import', [WorkLocationController::class, 'import'])->name('work-locations.import');
    Route::get('work-locations/export', [WorkLocationController::class, 'export'])->name('work-locations.export');
    Route::get('work-locations/import-template', [WorkLocationController::class, 'importTemplate'])->name('work-locations.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/WorkLocationImportExportTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/WorkLocations/Index.tsx` following the exact same pattern as Task 6 Step 8 (add `useState` import, add `ImportExportToolbar`/`ImportModal` imports, add `importModalOpen` state, insert the toolbar + modal into the existing `mb-3 flex flex-wrap items-center gap-3` div), using:

```tsx
                    <ImportExportToolbar
                        importUrl={route('work-locations.import')}
                        previewUrl={route('work-locations.import.preview')}
                        exportUrl={route('work-locations.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('work-locations.import')}
                    previewUrl={route('work-locations.import.preview')}
                    templateUrl={route('work-locations.import-template')}
                    title="Lokasi Kerja"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                    ]}
                />
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/WorkLocationImporter.php \
        app/Services/ImportExport/Exports/WorkLocationExporter.php \
        app/Http/Controllers/WorkLocationController.php \
        routes/web.php \
        resources/js/Pages/Master/WorkLocations/Index.tsx \
        tests/Feature/ImportExport/WorkLocationImportExportTest.php
git commit -m "feat: add import/export to Lokasi Kerja master data"
```

---

## Task 8: Departemen (Department) import/export

**Files:**
- Create: `app/Services/ImportExport/Imports/DepartmentImporter.php`
- Create: `app/Services/ImportExport/Exports/DepartmentExporter.php`
- Modify: `app/Http/Controllers/DepartmentController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/Departments/Index.tsx`
- Test (new): `tests/Feature/ImportExport/DepartmentImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3).
- Produces: routes `departments.import.preview`, `departments.import`, `departments.export`, `departments.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/DepartmentImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DepartmentImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Department::factory()->create();

        $response = $this->get(route('departments.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Department::factory()->create();

        $response = $this->get(route('departments.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('departments.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'departments.csv',
            "Nama\nLogistik"
        );

        $response = $this->post(route('departments.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('departments', ['name' => 'Logistik']);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Department::factory()->create(['name' => 'Operasional']);

        $file = UploadedFile::fake()->createWithContent(
            'departments.csv',
            "Nama\nOperasional"
        );

        $response = $this->post(route('departments.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/DepartmentImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create DepartmentImporter**

Create `app/Services/ImportExport/Imports/DepartmentImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Department;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class DepartmentImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Department::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama'];
    }
}
```

- [ ] **Step 4: Create DepartmentExporter**

Create `app/Services/ImportExport/Exports/DepartmentExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Department;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class DepartmentExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama'];
    }

    public function exportQuery(): Builder
    {
        return Department::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name];
    }
}
```

- [ ] **Step 5: Wire DepartmentController onto the trait**

Modify `app/Http/Controllers/DepartmentController.php` — replace the top of the file:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Department;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\DepartmentExporter;
use App\Services\ImportExport\Imports\DepartmentImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new DepartmentImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new DepartmentExporter();
    }

    protected function exportFileName(): string
    {
        return 'departments-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('departments', DepartmentController::class)->except(['show']);`, add:

```php
    Route::post('departments/import/preview', [DepartmentController::class, 'importPreview'])->name('departments.import.preview');
    Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import');
    Route::get('departments/export', [DepartmentController::class, 'export'])->name('departments.export');
    Route::get('departments/import-template', [DepartmentController::class, 'importTemplate'])->name('departments.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/DepartmentImportExportTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/Departments/Index.tsx` following the exact same pattern as Task 6 Step 8, using:

```tsx
                    <ImportExportToolbar
                        importUrl={route('departments.import')}
                        previewUrl={route('departments.import.preview')}
                        exportUrl={route('departments.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('departments.import')}
                    previewUrl={route('departments.import.preview')}
                    templateUrl={route('departments.import-template')}
                    title="Departemen"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                    ]}
                />
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/DepartmentImporter.php \
        app/Services/ImportExport/Exports/DepartmentExporter.php \
        app/Http/Controllers/DepartmentController.php \
        routes/web.php \
        resources/js/Pages/Master/Departments/Index.tsx \
        tests/Feature/ImportExport/DepartmentImportExportTest.php
git commit -m "feat: add import/export to Departemen master data"
```

---

## Task 9: Model Kendaraan (VehicleModel) import/export — composite uniqueKey

**Files:**
- Create: `app/Services/ImportExport/Imports/VehicleModelImporter.php`
- Create: `app/Services/ImportExport/Exports/VehicleModelExporter.php`
- Modify: `app/Http/Controllers/VehicleModelController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/VehicleModels/Index.tsx`
- Test (new): `tests/Feature/ImportExport/VehicleModelImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3), composite `uniqueKey(): array` support from Task 1.
- Produces: routes `vehicle-models.import.preview`, `vehicle-models.import`, `vehicle-models.export`, `vehicle-models.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/VehicleModelImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleModelImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create();

        $response = $this->get(route('vehicle-models.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create();

        $response = $this->get(route('vehicle-models.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('vehicle-models.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'vehicle-models.csv',
            "Nama,Merek,Suffix\nAvanza,Toyota,Veloz"
        );

        $response = $this->post(route('vehicle-models.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'brand' => 'Merek', 'suffix' => 'Suffix'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('vehicle_models', ['name' => 'Avanza', 'brand' => 'Toyota', 'suffix' => 'Veloz']);
    }

    public function test_import_skips_duplicate_name_and_brand_combination(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create(['name' => 'Xenia', 'brand' => 'Daihatsu']);

        $file = UploadedFile::fake()->createWithContent(
            'vehicle-models.csv',
            "Nama,Merek,Suffix\nXenia,Daihatsu,R"
        );

        $response = $this->post(route('vehicle-models.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'brand' => 'Merek', 'suffix' => 'Suffix'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }

    public function test_import_does_not_skip_same_name_with_different_brand(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        VehicleModel::factory()->create(['name' => 'Xenia', 'brand' => 'Daihatsu']);

        $file = UploadedFile::fake()->createWithContent(
            'vehicle-models.csv',
            "Nama,Merek,Suffix\nXenia,Toyota,R"
        );

        $response = $this->post(route('vehicle-models.import'), [
            'file' => $file,
            'column_mapping' => ['name' => 'Nama', 'brand' => 'Merek', 'suffix' => 'Suffix'],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'processed_rows' => 1, 'skipped_rows' => 0]);
        $this->assertDatabaseHas('vehicle_models', ['name' => 'Xenia', 'brand' => 'Toyota']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/VehicleModelImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create VehicleModelImporter**

Create `app/Services/ImportExport/Imports/VehicleModelImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class VehicleModelImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return VehicleModel::class;
    }

    public function uniqueKey(): string|array
    {
        return ['name', 'brand'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Merek', 'Suffix'];
    }
}
```

- [ ] **Step 4: Create VehicleModelExporter**

Create `app/Services/ImportExport/Exports/VehicleModelExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class VehicleModelExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Merek', 'Suffix'];
    }

    public function exportQuery(): Builder
    {
        return VehicleModel::query()->orderBy('brand')->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->brand, $model->suffix];
    }
}
```

- [ ] **Step 5: Wire VehicleModelController onto the trait**

Modify `app/Http/Controllers/VehicleModelController.php` — replace the top of the file:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\VehicleModelExporter;
use App\Services\ImportExport\Imports\VehicleModelImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleModelController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new VehicleModelImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new VehicleModelExporter();
    }

    protected function exportFileName(): string
    {
        return 'vehicle-models-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('vehicle-models', VehicleModelController::class)->except(['show']);`, add:

```php
    Route::post('vehicle-models/import/preview', [VehicleModelController::class, 'importPreview'])->name('vehicle-models.import.preview');
    Route::post('vehicle-models/import', [VehicleModelController::class, 'import'])->name('vehicle-models.import');
    Route::get('vehicle-models/export', [VehicleModelController::class, 'export'])->name('vehicle-models.export');
    Route::get('vehicle-models/import-template', [VehicleModelController::class, 'importTemplate'])->name('vehicle-models.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/VehicleModelImportExportTest.php`
Expected: PASS (6 tests) — in particular confirming composite dedupe: same `name`+`brand` skipped, same `name` with different `brand` inserted.

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/VehicleModels/Index.tsx` following the exact same pattern as Task 6 Step 8, using:

```tsx
                    <ImportExportToolbar
                        importUrl={route('vehicle-models.import')}
                        previewUrl={route('vehicle-models.import.preview')}
                        exportUrl={route('vehicle-models.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('vehicle-models.import')}
                    previewUrl={route('vehicle-models.import.preview')}
                    templateUrl={route('vehicle-models.import-template')}
                    title="Model Kendaraan"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'brand', label: 'Merek', required: true },
                        { key: 'suffix', label: 'Suffix', required: false },
                    ]}
                />
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/VehicleModelImporter.php \
        app/Services/ImportExport/Exports/VehicleModelExporter.php \
        app/Http/Controllers/VehicleModelController.php \
        routes/web.php \
        resources/js/Pages/Master/VehicleModels/Index.tsx \
        tests/Feature/ImportExport/VehicleModelImportExportTest.php
git commit -m "feat: add import/export to Model Kendaraan master data"
```

---

## Task 10: Supplier import/export — insertRow override creates the address

**Files:**
- Create: `app/Services/ImportExport/Imports/SupplierImporter.php`
- Create: `app/Services/ImportExport/Exports/SupplierExporter.php`
- Modify: `app/Http/Controllers/SupplierController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/Suppliers/Index.tsx`
- Test (new): `tests/Feature/ImportExport/SupplierImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3), overridable `insertRow()` from Task 1.
- Produces: routes `suppliers.import.preview`, `suppliers.import`, `suppliers.export`, `suppliers.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/SupplierImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SupplierImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Supplier::factory()->has(\App\Models\SupplierAddress::factory(), 'addresses')->create();

        $response = $this->get(route('suppliers.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Supplier::factory()->has(\App\Models\SupplierAddress::factory(), 'addresses')->create();

        $response = $this->get(route('suppliers.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('suppliers.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_supplier_and_primary_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'suppliers.csv',
            "Nama,Kontak,Email,Telepon,Jalan,Kota,Provinsi,KodePos,Negara\n"
            . "PT Sumber Jaya,Budi,budi@sumberjaya.test,08123456789,Jl. Industri 1,Bekasi,Jawa Barat,17530,Indonesia"
        );

        $response = $this->post(route('suppliers.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'contact_person' => 'Kontak',
                'email' => 'Email',
                'phone' => 'Telepon',
                'street' => 'Jalan',
                'city' => 'Kota',
                'state' => 'Provinsi',
                'postal_code' => 'KodePos',
                'country' => 'Negara',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('suppliers', ['name' => 'PT Sumber Jaya', 'email' => 'budi@sumberjaya.test']);
        $this->assertDatabaseHas('supplier_addresses', [
            'street' => 'Jl. Industri 1',
            'city' => 'Bekasi',
            'address_type' => 'primary',
        ]);
    }

    public function test_import_skips_duplicate_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Supplier::factory()->create(['name' => 'PT Existing']);

        $file = UploadedFile::fake()->createWithContent(
            'suppliers.csv',
            "Nama,Kontak,Email,Telepon,Jalan,Kota,Provinsi,KodePos,Negara\n"
            . "PT Existing,Budi,new-email@test.test,08123456789,Jl. Baru 1,Bekasi,Jawa Barat,17530,Indonesia"
        );

        $response = $this->post(route('suppliers.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'contact_person' => 'Kontak',
                'email' => 'Email',
                'phone' => 'Telepon',
                'street' => 'Jalan',
                'city' => 'Kota',
                'state' => 'Provinsi',
                'postal_code' => 'KodePos',
                'country' => 'Negara',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/SupplierImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create SupplierImporter with an overridden insertRow()**

Create `app/Services/ImportExport/Imports/SupplierImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class SupplierImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Supplier::class;
    }

    public function uniqueKey(): string|array
    {
        return 'name';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Kontak', 'Email', 'Telepon', 'Jalan', 'Kota', 'Provinsi', 'KodePos', 'Negara'];
    }

    public function insertRow(array $data): void
    {
        $supplier = Supplier::create([
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        $supplier->addresses()->create([
            'street' => $data['street'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            'address_type' => 'primary',
        ]);
    }
}
```

- [ ] **Step 4: Create SupplierExporter**

Create `app/Services/ImportExport/Exports/SupplierExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class SupplierExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Kontak', 'Email', 'Telepon', 'Jalan', 'Kota', 'Provinsi', 'KodePos', 'Negara'];
    }

    public function exportQuery(): Builder
    {
        return Supplier::query()->with('primaryAddress')->orderBy('name');
    }

    public function mapRow($model): array
    {
        $address = $model->primaryAddress;

        return [
            $model->name,
            $model->contact_person,
            $model->email,
            $model->phone,
            $address->street ?? '-',
            $address->city ?? '-',
            $address->state ?? '-',
            $address->postal_code ?? '-',
            $address->country ?? '-',
        ];
    }
}
```

- [ ] **Step 5: Wire SupplierController onto the trait**

Modify `app/Http/Controllers/SupplierController.php` — replace the top of the file (imports + class opening, up to but not including the docblock for `index()`):

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\SupplierExporter;
use App\Services\ImportExport\Imports\SupplierImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new SupplierImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new SupplierExporter();
    }

    protected function exportFileName(): string
    {
        return 'suppliers-export';
    }

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('suppliers', SupplierController::class);`, add:

```php
    Route::post('suppliers/import/preview', [SupplierController::class, 'importPreview'])->name('suppliers.import.preview');
    Route::post('suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
    Route::get('suppliers/export', [SupplierController::class, 'export'])->name('suppliers.export');
    Route::get('suppliers/import-template', [SupplierController::class, 'importTemplate'])->name('suppliers.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/SupplierImportExportTest.php`
Expected: PASS (5 tests) — in particular confirming the imported supplier gets a `supplier_addresses` row with `address_type = 'primary'`.

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/Suppliers/Index.tsx` following the exact same pattern as Task 6 Step 8 (add `useState` import, add toolbar imports, add `importModalOpen` state, insert into the existing `mb-3 flex flex-wrap items-center gap-3` div next to the "Tambah Supplier" button), using:

```tsx
                    <ImportExportToolbar
                        importUrl={route('suppliers.import')}
                        previewUrl={route('suppliers.import.preview')}
                        exportUrl={route('suppliers.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('suppliers.import')}
                    previewUrl={route('suppliers.import.preview')}
                    templateUrl={route('suppliers.import-template')}
                    title="Supplier"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'contact_person', label: 'Kontak Person', required: false },
                        { key: 'email', label: 'Email', required: true },
                        { key: 'phone', label: 'Telepon', required: false },
                        { key: 'street', label: 'Jalan', required: true },
                        { key: 'city', label: 'Kota', required: true },
                        { key: 'state', label: 'Provinsi', required: true },
                        { key: 'postal_code', label: 'Kode Pos', required: true },
                        { key: 'country', label: 'Negara', required: true },
                    ]}
                />
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/SupplierImporter.php \
        app/Services/ImportExport/Exports/SupplierExporter.php \
        app/Http/Controllers/SupplierController.php \
        routes/web.php \
        resources/js/Pages/Master/Suppliers/Index.tsx \
        tests/Feature/ImportExport/SupplierImportExportTest.php
git commit -m "feat: add import/export to Supplier master data"
```

---

## Task 11: Product import/export — multi foreign-key resolution

**Files:**
- Create: `app/Services/ImportExport/Imports/ProductImporter.php`
- Create: `app/Services/ImportExport/Exports/ProductExporter.php`
- Modify: `app/Http/Controllers/ProductController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/Products/Index.tsx`
- Test (new): `tests/Feature/ImportExport/ProductImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3), `resolveForeignKey()`/`RowTransformException` from Task 1.
- Produces: routes `products.import.preview`, `products.import`, `products.export`, `products.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/ProductImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create();

        $response = $this->get(route('products.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Product::factory()->create();

        $response = $this->get(route('products.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('products.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_resolves_relations_and_creates_product(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vehicleModel = VehicleModel::factory()->create(['name' => 'Avanza', 'brand' => 'Toyota']);
        $supplier = Supplier::factory()->create(['name' => 'PT Onderdil Jaya']);
        $category = ProductCategory::factory()->create(['name' => 'Body Parts']);
        $rack = Rack::factory()->create(['code' => 'A-01']);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "PartNumber,Nama,Merek,Model,Supplier,Kategori,Satuan,Deskripsi,Harga,Aktif,Rak\n"
            . "P0001-AVZ,Bumper Depan,Toyota,Avanza,PT Onderdil Jaya,Body Parts,pcs,Bumper original,250000,1,A-01"
        );

        $response = $this->post(route('products.import'), [
            'file' => $file,
            'column_mapping' => [
                'part_number' => 'PartNumber',
                'name' => 'Nama',
                'brand' => 'Merek',
                'model_kendaraan' => 'Model',
                'supplier' => 'Supplier',
                'kategori' => 'Kategori',
                'unit' => 'Satuan',
                'description' => 'Deskripsi',
                'base_price' => 'Harga',
                'is_active' => 'Aktif',
                'default_rack' => 'Rak',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'part_number' => 'P0001-AVZ',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'default_rack_id' => $rack->id,
        ]);
    }

    public function test_import_records_error_when_vehicle_model_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Supplier::factory()->create(['name' => 'PT Onderdil Jaya']);
        ProductCategory::factory()->create(['name' => 'Body Parts']);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "PartNumber,Nama,Merek,Model,Supplier,Kategori,Satuan\n"
            . "P0002-XXX,Lampu Belakang,Toyota,Model Tidak Ada,PT Onderdil Jaya,Body Parts,pcs"
        );

        $response = $this->post(route('products.import'), [
            'file' => $file,
            'column_mapping' => [
                'part_number' => 'PartNumber',
                'name' => 'Nama',
                'brand' => 'Merek',
                'model_kendaraan' => 'Model',
                'supplier' => 'Supplier',
                'kategori' => 'Kategori',
                'unit' => 'Satuan',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'processed_rows' => 0]);
        $this->assertDatabaseMissing('products', ['part_number' => 'P0002-XXX']);

        $log = \App\Services\ImportExport\Models\ImportLog::find($importLogId);
        $this->assertNotEmpty($log->errors);
    }

    public function test_import_skips_duplicate_part_number(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vehicleModel = VehicleModel::factory()->create(['name' => 'Avanza', 'brand' => 'Toyota']);
        $supplier = Supplier::factory()->create(['name' => 'PT Onderdil Jaya']);
        $category = ProductCategory::factory()->create(['name' => 'Body Parts']);
        Product::factory()->create([
            'part_number' => 'P0003-DUP',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "PartNumber,Nama,Merek,Model,Supplier,Kategori,Satuan\n"
            . "P0003-DUP,Bumper Depan,Toyota,Avanza,PT Onderdil Jaya,Body Parts,pcs"
        );

        $response = $this->post(route('products.import'), [
            'file' => $file,
            'column_mapping' => [
                'part_number' => 'PartNumber',
                'name' => 'Nama',
                'brand' => 'Merek',
                'model_kendaraan' => 'Model',
                'supplier' => 'Supplier',
                'kategori' => 'Kategori',
                'unit' => 'Satuan',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/ProductImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create ProductImporter**

Create `app/Services/ImportExport/Imports/ProductImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;

class ProductImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Product::class;
    }

    public function uniqueKey(): string|array
    {
        return 'part_number';
    }

    public function rules(): array
    {
        return [
            'part_number' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'vehicle_model_id' => ['required', 'exists:vehicle_models,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'default_rack_id' => ['nullable', 'exists:racks,id'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['PartNumber', 'Nama', 'Merek', 'Model', 'Supplier', 'Kategori', 'Satuan', 'Deskripsi', 'Harga', 'Aktif', 'Rak'];
    }

    public function transformRow(array $mapped): array
    {
        $brand = is_string($mapped['brand'] ?? null) ? trim($mapped['brand']) : null;
        $modelName = is_string($mapped['model_kendaraan'] ?? null) ? trim($mapped['model_kendaraan']) : null;

        $vehicleModel = VehicleModel::where('brand', $brand)->where('name', $modelName)->first();

        if (! $vehicleModel) {
            throw new RowTransformException(
                'Model kendaraan "'.$brand.' '.$modelName.'" tidak ditemukan.'
            );
        }

        $mapped['vehicle_model_id'] = $vehicleModel->id;
        $mapped['supplier_id'] = $this->resolveForeignKey(Supplier::class, 'name', $mapped['supplier'] ?? null);
        $mapped['category_id'] = $this->resolveForeignKey(ProductCategory::class, 'name', $mapped['kategori'] ?? null);
        $mapped['default_rack_id'] = $this->resolveForeignKey(Rack::class, 'code', $mapped['default_rack'] ?? null, required: false);
        $mapped['is_active'] = filter_var($mapped['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        unset($mapped['brand'], $mapped['model_kendaraan'], $mapped['supplier'], $mapped['kategori'], $mapped['default_rack']);

        return $mapped;
    }
}
```

- [ ] **Step 4: Create ProductExporter**

Create `app/Services/ImportExport/Exports/ProductExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Product;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ProductExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Part Number', 'Nama', 'Model Kendaraan', 'Supplier', 'Kategori', 'Satuan', 'Harga', 'Aktif'];
    }

    public function exportQuery(): Builder
    {
        return Product::query()->with(['vehicleModel', 'supplier', 'category'])->orderBy('part_number');
    }

    public function mapRow($model): array
    {
        return [
            $model->part_number,
            $model->name,
            trim(($model->vehicleModel->brand ?? '').' '.($model->vehicleModel->name ?? '')),
            $model->supplier->name ?? '-',
            $model->category->name ?? '-',
            $model->unit,
            $model->base_price,
            $model->is_active ? 'Ya' : 'Tidak',
        ];
    }
}
```

- [ ] **Step 5: Wire ProductController onto the trait**

Modify `app/Http/Controllers/ProductController.php` — replace the top of the file:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\ProductExporter;
use App\Services\ImportExport\Imports\ProductImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new ProductImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new ProductExporter();
    }

    protected function exportFileName(): string
    {
        return 'products-export';
    }

    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('products', ProductController::class);`, add:

```php
    Route::post('products/import/preview', [ProductController::class, 'importPreview'])->name('products.import.preview');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('products/import-template', [ProductController::class, 'importTemplate'])->name('products.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/ProductImportExportTest.php`
Expected: PASS (6 tests) — in particular confirming FK resolution succeeds, records a row error (not a crash) when the vehicle model lookup fails, and dedupe on `part_number` works.

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/Products/Index.tsx` — add these two imports right after the existing `EmptyState` import:

```tsx
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';
```

Change `import React from 'react';` to `import React, { useState } from 'react';`.

Add `const [importModalOpen, setImportModalOpen] = useState(false);` as the first line inside the `Index` function body.

Find the existing `<div className="mb-3"><Link href={route('products.create')}>` block (the one placed after the filter row) and replace it with:

```tsx
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('products.create')}>
                        <Button>Tambah Produk</Button>
                    </Link>
                    <ImportExportToolbar
                        importUrl={route('products.import')}
                        previewUrl={route('products.import.preview')}
                        exportUrl={route('products.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('products.import')}
                    previewUrl={route('products.import.preview')}
                    templateUrl={route('products.import-template')}
                    title="Produk"
                    fields={[
                        { key: 'part_number', label: 'Part Number', required: true },
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'brand', label: 'Merek', required: true },
                        { key: 'model_kendaraan', label: 'Model Kendaraan', required: true },
                        { key: 'supplier', label: 'Supplier', required: true },
                        { key: 'kategori', label: 'Kategori', required: true },
                        { key: 'unit', label: 'Satuan', required: true },
                        { key: 'description', label: 'Deskripsi', required: false },
                        { key: 'base_price', label: 'Harga Dasar', required: false },
                        { key: 'is_active', label: 'Aktif', required: false },
                        { key: 'default_rack', label: 'Rak Default', required: false },
                    ]}
                />
```

Note: keep whatever closing markup followed the original `Tambah Produk` block (e.g. the button's closing `</Link></div>`) consistent — only the opening `<div>`/toolbar content changes, the rest of the page (table, pagination) stays as-is. Check the current file content before editing to preserve exact surrounding structure.

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Services/ImportExport/Imports/ProductImporter.php \
        app/Services/ImportExport/Exports/ProductExporter.php \
        app/Http/Controllers/ProductController.php \
        routes/web.php \
        resources/js/Pages/Master/Products/Index.tsx \
        tests/Feature/ImportExport/ProductImportExportTest.php
git commit -m "feat: add import/export to Product master data"
```

---

## Task 12: Karyawan (Employee) import/export — optional FK resolution + fixed audit fields

**Files:**
- Create: `app/Services/ImportExport/Imports/EmployeeImporter.php`
- Create: `app/Services/ImportExport/Exports/EmployeeExporter.php`
- Modify: `app/Http/Controllers/EmployeeController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Master/Employees/Index.tsx`
- Test (new): `tests/Feature/ImportExport/EmployeeImportExportTest.php`

**Interfaces:**
- Consumes: `HasImportExport` trait (Task 2), `ImportModal` (Task 3), `fixedFields()`/optional `resolveForeignKey()` from Task 1.
- Produces: routes `employees.import.preview`, `employees.import`, `employees.export`, `employees.import-template`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportExport/EmployeeImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Employee::factory()->create();

        $response = $this->get(route('employees.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Employee::factory()->create();

        $response = $this->get(route('employees.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('employees.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_resolves_optional_relations_and_stamps_audit_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $position = JobPosition::factory()->create(['name' => 'Staff Gudang']);
        $location = WorkLocation::factory()->create(['name' => 'Gudang Utama']);
        $department = Department::factory()->create(['name' => 'Logistik']);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Telepon,Email,Status\n"
            . "Andi Saputra,NIK-0001,Staff Gudang,Gudang Utama,Logistik,08123456789,andi@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'nik' => 'NIK-0001',
            'job_position_id' => $position->id,
            'work_location_id' => $location->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_import_leaves_relation_null_when_optional_lookup_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Telepon,Email,Status\n"
            . "Budi Santoso,NIK-0002,Jabatan Tidak Ada,,,08123456780,budi@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'nik' => 'NIK-0002',
            'job_position_id' => null,
            'work_location_id' => null,
            'department_id' => null,
        ]);
    }

    public function test_import_skips_duplicate_nik(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Employee::factory()->create(['nik' => 'NIK-0003']);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Telepon,Email,Status\n"
            . "Citra Dewi,NIK-0003,,,,08123456781,citra@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ImportExport/EmployeeImportExportTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create EmployeeImporter**

Create `app/Services/ImportExport/Imports/EmployeeImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class EmployeeImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Employee::class;
    }

    public function uniqueKey(): string|array
    {
        return 'nik';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'job_position_id' => ['nullable', 'exists:job_positions,id'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', 'in:Aktif,Nonaktif'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'NIK', 'Jabatan', 'Lokasi', 'Departemen', 'Telepon', 'Email', 'Status'];
    }

    public function fixedFields(int $userId): array
    {
        return [
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    public function transformRow(array $mapped): array
    {
        $mapped['job_position_id'] = $this->resolveForeignKey(JobPosition::class, 'name', $mapped['job_position'] ?? null, required: false);
        $mapped['work_location_id'] = $this->resolveForeignKey(WorkLocation::class, 'name', $mapped['work_location'] ?? null, required: false);
        $mapped['department_id'] = $this->resolveForeignKey(Department::class, 'name', $mapped['department'] ?? null, required: false);
        $mapped['status'] = $mapped['status'] ?: 'Aktif';

        unset($mapped['job_position'], $mapped['work_location'], $mapped['department']);

        return $mapped;
    }
}
```

- [ ] **Step 4: Create EmployeeExporter**

Create `app/Services/ImportExport/Exports/EmployeeExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Employee;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class EmployeeExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'NIK', 'Jabatan', 'Lokasi', 'Departemen', 'Telepon', 'Email', 'Status'];
    }

    public function exportQuery(): Builder
    {
        return Employee::query()->with(['jobPosition', 'workLocation', 'department'])->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [
            $model->name,
            $model->nik,
            $model->jobPosition->name ?? '-',
            $model->workLocation->name ?? '-',
            $model->department->name ?? '-',
            $model->phone,
            $model->email,
            $model->status,
        ];
    }
}
```

- [ ] **Step 5: Wire EmployeeController onto the trait**

Modify `app/Http/Controllers/EmployeeController.php` — replace the top of the file (imports block only, keep everything from `public function index` onward unchanged):

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\EmployeeExporter;
use App\Services\ImportExport\Imports\EmployeeImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new EmployeeImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new EmployeeExporter();
    }

    protected function exportFileName(): string
    {
        return 'employees-export';
    }

    public function index(Request $request)
    {
```

- [ ] **Step 6: Add routes**

Modify `routes/web.php` — right after the line `Route::resource('employees', EmployeeController::class);`, add:

```php
    Route::post('employees/import/preview', [EmployeeController::class, 'importPreview'])->name('employees.import.preview');
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.import-template');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/EmployeeImportExportTest.php`
Expected: PASS (6 tests) — in particular confirming `created_by`/`updated_by` are stamped from the importing user, optional relation lookups that don't match leave the FK `null` rather than failing the row, and `nik` dedupe works.

- [ ] **Step 8: Wire the frontend toolbar and modal**

Modify `resources/js/Pages/Master/Employees/Index.tsx` following the exact same pattern as Task 6 Step 8, using:

```tsx
                    <ImportExportToolbar
                        importUrl={route('employees.import')}
                        previewUrl={route('employees.import.preview')}
                        exportUrl={route('employees.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('employees.import')}
                    previewUrl={route('employees.import.preview')}
                    templateUrl={route('employees.import-template')}
                    title="Karyawan"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'nik', label: 'NIK', required: false },
                        { key: 'job_position', label: 'Jabatan', required: false },
                        { key: 'work_location', label: 'Lokasi Kerja', required: false },
                        { key: 'department', label: 'Departemen', required: false },
                        { key: 'phone', label: 'Telepon', required: false },
                        { key: 'email', label: 'Email', required: false },
                        { key: 'status', label: 'Status (Aktif/Nonaktif)', required: true },
                    ]}
                />
```

- [ ] **Step 9: Verify the frontend build is clean**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 10: Run the full backend test suite for a final regression check**

Run: `php artisan test`
Expected: All ImportExport-related tests pass (Tasks 1-12), and the failure count matches the pre-existing baseline (the same handful of unrelated Auth/ExampleTest/ProductController failures noted in earlier work on this project — no *new* failures introduced by this feature).

- [ ] **Step 11: Commit**

```bash
git add app/Services/ImportExport/Imports/EmployeeImporter.php \
        app/Services/ImportExport/Exports/EmployeeExporter.php \
        app/Http/Controllers/EmployeeController.php \
        routes/web.php \
        resources/js/Pages/Master/Employees/Index.tsx \
        tests/Feature/ImportExport/EmployeeImportExportTest.php
git commit -m "feat: add import/export to Karyawan master data"
```
