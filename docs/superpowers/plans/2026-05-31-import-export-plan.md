# Import/Export System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build reusable import/export foundation with User import/export as the reference implementation.

**Architecture:** Generic abstract base classes (BaseImporter/BaseExporter) with model-level contracts (Importable/Exportable), all orchestrated through Manager classes that dispatch queue jobs. Per-row validation with error collection; insert-only duplicate skipping.

**Tech Stack:** Laravel 11, maatwebsite/excel ^3.1, barryvdh/laravel-dompdf, React/TypeScript/Inertia, Tailwind CSS

---

## File Structure Map

```
Create: app/Services/ImportExport/Enums/ImportFormat.php
Create: app/Services/ImportExport/Enums/ExportFormat.php
Create: app/Services/ImportExport/Enums/ImportStatus.php
Create: app/Services/ImportExport/DTOs/ImportConfig.php
Create: app/Services/ImportExport/DTOs/ExportConfig.php
Create: app/Services/ImportExport/Contracts/Importable.php
Create: app/Services/ImportExport/Contracts/Exportable.php
Create: app/Services/ImportExport/Models/ImportLog.php
Create: app/Services/ImportExport/Jobs/ProcessImport.php
Create: app/Services/ImportExport/Base/BaseImporter.php
Create: app/Services/ImportExport/Base/BaseExporter.php
Create: app/Services/ImportExport/Managers/ImportManager.php
Create: app/Services/ImportExport/Managers/ExportManager.php
Create: app/Services/ImportExport/Imports/UserImporter.php
Create: app/Services/ImportExport/Exports/UserExporter.php
Create: app/Models/ImportLog.php (extends Services one, for Laravel conventions)
Create: database/migrations/2026_05_31_000001_create_import_logs_table.php
Create: database/factories/ImportLogFactory.php
Create: resources/js/Components/ImportExport/ImportButton.tsx
Create: resources/js/Components/ImportExport/ImportModal.tsx
Create: resources/js/Components/ImportExport/ImportProgress.tsx
Create: resources/js/Components/ImportExport/ExportButton.tsx
Create: resources/js/Components/ImportExport/ImportExportToolbar.tsx
Create: tests/Feature/ImportExport/ImportManagerTest.php
Create: tests/Feature/ImportExport/ExportManagerTest.php
Create: tests/Feature/ImportExport/ProcessImportTest.php
Create: tests/Unit/ImportExport/ImportConfigTest.php
Create: tests/Unit/ImportExport/EnumsTest.php

Modify: app/Http/Controllers/UserController.php (add import/export methods)
Modify: routes/web.php (add import/export routes)
Modify: resources/js/Pages/Users/Index.tsx (add ImportExportToolbar, ImportModal)
```

---

## Phase 1: Foundation

### Task 1: Install composer packages

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Require maatwebsite/excel and barryvdh/laravel-dompdf**

Run:
```bash
composer require maatwebsite/excel barryvdh/laravel-dompdf
```

- [ ] **Step 2: Publish configs (optional — verify they exist)**

Run:
```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock config/excel.php
git commit -m "feat: add maatwebsite/excel and barryvdh/laravel-dompdf dependencies"
```

---

### Task 2: Create Enums

**Files:**
- Create: `app/Services/ImportExport/Enums/ImportFormat.php`
- Create: `app/Services/ImportExport/Enums/ExportFormat.php`
- Create: `app/Services/ImportExport/Enums/ImportStatus.php`

- [ ] **Step 1: Create ImportFormat enum**

```php
<?php

namespace App\Services\ImportExport\Enums;

enum ImportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';
}
```

File: `app/Services/ImportExport/Enums/ImportFormat.php`

- [ ] **Step 2: Create ExportFormat enum**

```php
<?php

namespace App\Services\ImportExport\Enums;

enum ExportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case Pdf = 'pdf';
}
```

File: `app/Services/ImportExport/Enums/ExportFormat.php`

- [ ] **Step 3: Create ImportStatus enum**

```php
<?php

namespace App\Services\ImportExport\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

File: `app/Services/ImportExport/Enums/ImportStatus.php`

- [ ] **Step 4: Commit**

```bash
git add app/Services/ImportExport/Enums/
git commit -m "feat: add ImportFormat, ExportFormat, and ImportStatus enums"
```

---

### Task 3: Create DTOs

**Files:**
- Create: `app/Services/ImportExport/DTOs/ImportConfig.php`
- Create: `app/Services/ImportExport/DTOs/ExportConfig.php`

- [ ] **Step 1: Create ImportConfig DTO**

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
        public string $uniqueKey,
        public int $chunkSize = 500,
    ) {}
}
```

File: `app/Services/ImportExport/DTOs/ImportConfig.php`

- [ ] **Step 2: Create ExportConfig DTO**

```php
<?php

namespace App\Services\ImportExport\DTOs;

use App\Services\ImportExport\Enums\ExportFormat;

readonly class ExportConfig
{
    public function __construct(
        public ExportFormat $format,
        public string $fileName,
        public array $headings,
        public array $columns,
        public string $exportableClass,
        /** @var array<string, mixed> */
        public array $filters = [],
    ) {}
}
```

File: `app/Services/ImportExport/DTOs/ExportConfig.php`

- [ ] **Step 3: Commit**

```bash
git add app/Services/ImportExport/DTOs/
git commit -m "feat: add ImportConfig and ExportConfig DTOs"
```

---

### Task 4: Create ImportLog migration, model, and factory

**Files:**
- Create: `database/migrations/2026_05_31_000001_create_import_logs_table.php`
- Create: `app/Services/ImportExport/Models/ImportLog.php`
- Create: `app/Models/ImportLog.php`
- Create: `database/factories/ImportLogFactory.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->string('file_name');
            $table->string('file_format');
            $table->string('status')->default('pending');
            $table->json('column_mapping')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
```

File: `database/migrations/2026_05_31_000001_create_import_logs_table.php`

- [ ] **Step 2: Create service-layer ImportLog model**

```php
<?php

namespace App\Services\ImportExport\Models;

use App\Services\ImportExport\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $fillable = [
        'user_id',
        'model_type',
        'file_name',
        'file_format',
        'status',
        'column_mapping',
        'total_rows',
        'processed_rows',
        'skipped_rows',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'errors' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'skipped_rows' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
```

File: `app/Services/ImportExport/Models/ImportLog.php`

- [ ] **Step 3: Create app/Models alias**

```php
<?php

namespace App\Models;

class ImportLog extends \App\Services\ImportExport\Models\ImportLog {}
```

File: `app/Models/ImportLog.php`

- [ ] **Step 4: Create factory**

```php
<?php

namespace Database\Factories;

use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'model_type' => \App\Models\User::class,
            'file_name' => fake()->word() . '.xlsx',
            'file_format' => 'xlsx',
            'status' => ImportStatus::Pending->value,
            'column_mapping' => ['email' => 'A', 'name' => 'B'],
            'total_rows' => fake()->numberBetween(10, 1000),
            'processed_rows' => 0,
            'skipped_rows' => 0,
            'errors' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Completed->value,
            'processed_rows' => $attributes['total_rows'],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Failed->value,
            'errors' => [['row' => 1, 'field' => 'email', 'message' => 'Invalid email']],
        ]);
    }
}
```

File: `database/factories/ImportLogFactory.php`

- [ ] **Step 5: Run migration and commit**

```bash
php artisan migrate
git add database/migrations/2026_05_31_000001_create_import_logs_table.php app/Services/ImportExport/Models/ImportLog.php app/Models/ImportLog.php database/factories/ImportLogFactory.php
git commit -m "feat: add ImportLog model, migration, and factory"
```

---

## Phase 2: Contracts & Jobs

### Task 5: Create contracts

**Files:**
- Create: `app/Services/ImportExport/Contracts/Importable.php`
- Create: `app/Services/ImportExport/Contracts/Exportable.php`

- [ ] **Step 1: Create Importable contract**

```php
<?php

namespace App\Services\ImportExport\Contracts;

interface Importable
{
    /** @return array<string, array<int, string>> Laravel validation rules keyed by column */
    public function rules(): array;

    /** Column used to detect duplicate rows (e.g. 'email') */
    public function uniqueKey(): string;

    /** Rows to process per queue chunk */
    public function chunkSize(): int;
}
```

File: `app/Services/ImportExport/Contracts/Importable.php`

- [ ] **Step 2: Create Exportable contract**

```php
<?php

namespace App\Services\ImportExport\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface Exportable
{
    /** @return array<string> Column heading labels */
    public function headings(): array;

    /** Base query builder for the export dataset */
    public function exportQuery(): Builder;

    /** @return array<string, mixed> Map a single model to a flat row */
    public function mapRow($model): array;
}
```

File: `app/Services/ImportExport/Contracts/Exportable.php`

- [ ] **Step 3: Commit**

```bash
git add app/Services/ImportExport/Contracts/
git commit -m "feat: add Importable and Exportable contracts"
```

---

### Task 6: Create ProcessImport job

**Files:**
- Create: `app/Services/ImportExport/Jobs/ProcessImport.php`

- [ ] **Step 1: Create the job**

```php
<?php

namespace App\Services\ImportExport\Jobs;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportStatus;
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

        $chunks = [];
        $chunkIndex = 0;

        Excel::filter('chunk')
            ->load($this->config->filePath)
            ->chunk($this->config->chunkSize, function ($rows) use ($importLog, &$chunkIndex) {
                $errors = $importLog->errors ?? [];
                $skipped = $importLog->skipped_rows;
                $processed = $importLog->processed_rows;

                $rowsArray = $rows->toArray();
                $headers = array_keys($rowsArray[0] ?? []);
                $importLog->increment('total_rows', count($rowsArray));

                foreach ($rowsArray as $rowIndex => $row) {
                    $mapped = $this->mapRow($row, $headers);

                    $validator = Validator::make($mapped, $this->config->validationRules);

                    if ($validator->fails()) {
                        foreach ($validator->errors()->toArray() as $field => $msgs) {
                            foreach ($msgs as $msg) {
                                $errors[] = [
                                    'row' => ($chunkIndex * $this->config->chunkSize) + $rowIndex + 1,
                                    'field' => $field,
                                    'message' => $msg,
                                ];
                            }
                        }
                        continue;
                    }

                    if ($this->isDuplicate($mapped)) {
                        $skipped++;
                        continue;
                    }

                    $this->insertRow($mapped);
                    $processed++;
                }

                $importLog->update([
                    'processed_rows' => $processed,
                    'skipped_rows' => $skipped,
                    'errors' => $errors,
                ]);

                $chunkIndex++;
            });

        $importLog->update([
            'status' => ImportStatus::Completed->value,
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
                $mapped[$field] = $row[$index] ?? null;
            }
        }
        return $mapped;
    }

    private function isDuplicate(array $data): bool
    {
        $key = $this->config->uniqueKey;
        if (!isset($data[$key]) || empty($data[$key])) {
            return false;
        }

        $modelClass = $this->config->modelType;
        return $modelClass::where($key, $data[$key])->exists();
    }

    private function insertRow(array $data): void
    {
        $modelClass = $this->config->modelType;
        $modelClass::create($data);
    }
}
```

File: `app/Services/ImportExport/Jobs/ProcessImport.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Jobs/ProcessImport.php
git commit -m "feat: add ProcessImport queued job"
```

---

## Phase 3: Base Classes

### Task 7: Create BaseImporter

**Files:**
- Create: `app/Services/ImportExport/Base/BaseImporter.php`

- [ ] **Step 1: Create BaseImporter**

```php
<?php

namespace App\Services\ImportExport\Base;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

abstract class BaseImporter
{
    abstract public function modelType(): string;

    abstract public function uniqueKey(): string;

    abstract public function rules(): array;

    public function chunkSize(): int
    {
        return 500;
    }

    public function preview(UploadedFile $file): array
    {
        $path = $file->store('imports/temp');

        $rows = Excel::toArray([], Storage::path($path))[0] ?? [];

        $headers = !empty($rows) ? array_keys($rows[0]) : [];
        $samples = array_slice($rows, 0, 3);

        Storage::delete($path);

        return [
            'headers' => $headers,
            'samples' => $samples,
            'total_rows' => count($rows),
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
            chunkSize: $this->chunkSize(),
        );
    }
}
```

File: `app/Services/ImportExport/Base/BaseImporter.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Base/BaseImporter.php
git commit -m "feat: add BaseImporter abstract class"
```

---

### Task 8: Create BaseExporter

**Files:**
- Create: `app/Services/ImportExport/Base/BaseExporter.php`

- [ ] **Step 1: Create BaseExporter**

```php
<?php

namespace App\Services\ImportExport\Base;

use App\Services\ImportExport\Contracts\Exportable;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseExporter implements Exportable
{
    abstract public function headings(): array;

    abstract public function exportQuery(): \Illuminate\Database\Eloquent\Builder;

    abstract public function mapRow($model): array;

    public function modelType(): string
    {
        return get_class($this->exportQuery()->getModel());
    }

    public function download(ExportConfig $config): BinaryFileResponse|StreamedResponse
    {
        return match ($config->format) {
            ExportFormat::Xlsx => $this->downloadXlsx($config),
            ExportFormat::Csv  => $this->downloadCsv($config),
            ExportFormat::Pdf  => $this->downloadPdf($config),
        };
    }

    private function downloadXlsx(ExportConfig $config): BinaryFileResponse|StreamedResponse
    {
        $rows = $this->buildRows();

        $export = new class($rows, $config->headings) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        return Excel::download($export, $config->fileName . '.xlsx');
    }

    private function downloadCsv(ExportConfig $config): StreamedResponse
    {
        $rows = $this->buildRows();

        $response = new StreamedResponse(function () use ($config, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $config->headings);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $config->fileName . '.csv"');

        return $response;
    }

    private function downloadPdf(ExportConfig $config): BinaryFileResponse|StreamedResponse
    {
        $rows = $this->buildRows();

        $pdf = Pdf::loadView('exports.table', [
            'headings' => $config->headings,
            'rows' => $rows,
            'title' => $config->fileName,
        ]);

        return $pdf->download($config->fileName . '.pdf');
    }

    private function buildRows(): array
    {
        return $this->exportQuery()->get()->map(fn ($model) => $this->mapRow($model))->toArray();
    }
}
```

File: `app/Services/ImportExport/Base/BaseExporter.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Base/BaseExporter.php
git commit -m "feat: add BaseExporter abstract class"
```

---

## Phase 4: Managers

### Task 9: Create ImportManager

**Files:**
- Create: `app/Services/ImportExport/Managers/ImportManager.php`

- [ ] **Step 1: Create ImportManager**

```php
<?php

namespace App\Services\ImportExport\Managers;

use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Jobs\ProcessImport;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Http\UploadedFile;

class ImportManager
{
    public function preview(BaseImporter $importer, UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            throw new \InvalidArgumentException('Unsupported file format. Accepted: xlsx, csv');
        }

        return $importer->preview($file);
    }

    public function start(
        BaseImporter $importer,
        UploadedFile $file,
        array $columnMapping,
        int $userId,
    ): ImportLog {
        $config = $importer->buildConfig($file, $columnMapping);

        $importLog = ImportLog::create([
            'user_id' => $userId,
            'model_type' => $config->modelType,
            'file_name' => $file->getClientOriginalName(),
            'file_format' => $config->format->value,
            'column_mapping' => $config->columnMapping,
            'status' => \App\Services\ImportExport\Enums\ImportStatus::Pending->value,
        ]);

        ProcessImport::dispatch($config, $importLog->id);

        return $importLog;
    }

    public function status(int $importLogId): ImportLog
    {
        return ImportLog::findOrFail($importLogId);
    }

    public function history(int $userId, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ImportLog::where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }
}
```

File: `app/Services/ImportExport/Managers/ImportManager.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Managers/ImportManager.php
git commit -m "feat: add ImportManager"
```

---

### Task 10: Create ExportManager

**Files:**
- Create: `app/Services/ImportExport/Managers/ExportManager.php`

- [ ] **Step 1: Create ExportManager**

```php
<?php

namespace App\Services\ImportExport\Managers;

use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\DTOs\ExportConfig;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportManager
{
    public function download(BaseExporter $exporter, ExportConfig $config): BinaryFileResponse|StreamedResponse
    {
        $count = $exporter->exportQuery()->count();

        if ($count === 0) {
            throw new \RuntimeException('No data to export.');
        }

        return $exporter->download($config);
    }
}
```

File: `app/Services/ImportExport/Managers/ExportManager.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Managers/ExportManager.php
git commit -m "feat: add ExportManager"
```

---

## Phase 5: Reference Implementation (Users)

### Task 11: Create UserImporter

**Files:**
- Create: `app/Services/ImportExport/Imports/UserImporter.php`

- [ ] **Step 1: Create UserImporter**

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\User;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class UserImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return User::class;
    }

    public function uniqueKey(): string
    {
        return 'email';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
```

File: `app/Services/ImportExport/Imports/UserImporter.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Imports/UserImporter.php
git commit -m "feat: add UserImporter reference implementation"
```

---

### Task 12: Create UserExporter

**Files:**
- Create: `app/Services/ImportExport/Exports/UserExporter.php`

- [ ] **Step 1: Create UserExporter**

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\User;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class UserExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Name', 'Email', 'Role', 'Created At'];
    }

    public function exportQuery(): Builder
    {
        return User::with('roles');
    }

    public function mapRow($model): array
    {
        return [
            $model->name,
            $model->email,
            $model->roles->pluck('name')->implode(', '),
            $model->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

File: `app/Services/ImportExport/Exports/UserExporter.php`

- [ ] **Step 2: Commit**

```bash
git add app/Services/ImportExport/Exports/UserExporter.php
git commit -m "feat: add UserExporter reference implementation"
```

---

### Task 13: Add routes and UserController methods

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/UserController.php`

- [ ] **Step 1: Add routes to web.php**

After the existing `Route::resource('users', UserController::class);` line, add:

```php
Route::post('users/import/preview', [UserController::class, 'importPreview'])->name('users.import.preview');
Route::post('users/import', [UserController::class, 'import'])->name('users.import');
Route::get('users/export', [UserController::class, 'export'])->name('users.export');
Route::get('import-status/{importLog}', [UserController::class, 'importStatus'])->name('import.status');
```

File: `routes/web.php` — inside the existing `Route::middleware('auth')->group(...)` block.

- [ ] **Step 2: Add controller methods to UserController**

Add these use statements at the top:

```php
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\UserExporter;
use App\Services\ImportExport\Imports\UserImporter;
use App\Services\ImportExport\Managers\ExportManager;
use App\Services\ImportExport\Managers\ImportManager;
use App\Services\ImportExport\Models\ImportLog;
```

Add these methods inside the class:

```php
public function importPreview(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
    ]);

    $manager = app(ImportManager::class);
    $result = $manager->preview(new UserImporter(), $request->file('file'));

    return response()->json($result);
}

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        'column_mapping' => 'required|array',
    ]);

    $manager = app(ImportManager::class);
    $importLog = $manager->start(
        new UserImporter(),
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
    $format = ExportFormat::from($request->query('format', 'xlsx'));

    $exporter = new UserExporter();

    $config = new \App\Services\ImportExport\DTOs\ExportConfig(
        format: $format,
        fileName: 'users-export-' . now()->format('Y-m-d-His'),
        headings: $exporter->headings(),
        columns: ['name', 'email', 'role', 'created_at'],
        exportableClass: UserExporter::class,
    );

    $manager = app(ExportManager::class);
    return $manager->download($exporter, $config);
}

public function importStatus(ImportLog $importLog)
{
    return response()->json([
        'id' => $importLog->id,
        'status' => $importLog->status,
        'total_rows' => $importLog->total_rows,
        'processed_rows' => $importLog->processed_rows,
        'skipped_rows' => $importLog->skipped_rows,
        'errors' => $importLog->errors,
    ]);
}
```

File: `app/Http/Controllers/UserController.php`

- [ ] **Step 3: Commit**

```bash
git add routes/web.php app/Http/Controllers/UserController.php
git commit -m "feat: add import/export routes and UserController methods"
```

---

## Phase 6: Frontend Components

### Task 14: Create ImportButton, ImportModal, and ImportProgress

**Files:**
- Create: `resources/js/Components/ImportExport/ImportProgress.tsx`
- Create: `resources/js/Components/ImportExport/ImportButton.tsx`
- Create: `resources/js/Components/ImportExport/ImportModal.tsx`

- [ ] **Step 1: Create ImportProgress**

```tsx
import React, { useEffect, useState } from 'react';

interface ImportProgressProps {
  importLogId: number;
  onComplete?: () => void;
}

interface ImportStatus {
  status: string;
  total_rows: number;
  processed_rows: number;
  skipped_rows: number;
  errors: Array<{ row: number; field: string; message: string }>;
}

export default function ImportProgress({ importLogId, onComplete }: ImportProgressProps) {
  const [status, setStatus] = useState<ImportStatus | null>(null);

  useEffect(() => {
    const poll = setInterval(async () => {
      try {
        const res = await fetch(route('import.status', importLogId));
        const data = await res.json();
        setStatus(data);

        if (data.status === 'completed' || data.status === 'failed') {
          clearInterval(poll);
          onComplete?.();
        }
      } catch {
        // polling will retry
      }
    }, 3000);

    return () => clearInterval(poll);
  }, [importLogId]);

  if (!status) return null;

  const pct = status.total_rows > 0
    ? Math.round((status.processed_rows / status.total_rows) * 100)
    : 0;

  return (
    <div className="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
      <div className="flex justify-between items-center mb-2">
        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
          {status.status === 'processing' ? 'Importing...' : status.status === 'completed' ? 'Completed' : 'Failed'}
        </span>
        <span className="text-xs text-gray-500">
          {status.processed_rows} / {status.total_rows} rows
        </span>
      </div>
      <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-500 ${
            status.status === 'failed' ? 'bg-red-500' : status.status === 'completed' ? 'bg-green-500' : 'bg-brand-500'
          }`}
          style={{ width: `${status.status === 'completed' ? 100 : pct}%` }}
        />
      </div>
      {(status.skipped_rows > 0 || (status.errors && status.errors.length > 0)) && (
        <div className="mt-2 text-xs text-gray-500">
          {status.skipped_rows > 0 && <span>{status.skipped_rows} duplicates skipped</span>}
          {status.errors && status.errors.length > 0 && (
            <span className="ml-3 text-red-500">{status.errors.length} errors</span>
          )}
        </div>
      )}
    </div>
  );
}
```

File: `resources/js/Components/ImportExport/ImportProgress.tsx`

- [ ] **Step 2: Create ImportButton**

```tsx
import React from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';

interface ImportButtonProps {
  onClick: () => void;
  disabled?: boolean;
}

export default function ImportButton({ onClick, disabled }: ImportButtonProps) {
  return (
    <Button variant="outline" onClick={onClick} disabled={disabled}>
      Import
    </Button>
  );
}
```

File: `resources/js/Components/ImportExport/ImportButton.tsx`

- [ ] **Step 3: Create ImportModal**

```tsx
import React, { useState, useRef } from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';
import ImportProgress from './ImportProgress';

interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete?: () => void;
  importUrl: string;
  previewUrl: string;
}

export default function ImportModal({ isOpen, onClose, onComplete, importUrl, previewUrl }: ImportModalProps) {
  const [step, setStep] = useState<'upload' | 'mapping' | 'importing'>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [headers, setHeaders] = useState<string[]>([]);
  const [samples, setSamples] = useState<Record<string, string>[]>([]);
  const [totalRows, setTotalRows] = useState(0);
  const [columnMapping, setColumnMapping] = useState<Record<string, string>>({});
  const [importLogId, setImportLogId] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  if (!isOpen) return null;

  const systemFields = [
    { key: 'name', label: 'Name', required: true },
    { key: 'email', label: 'Email', required: true },
    { key: 'password', label: 'Password', required: true },
  ];

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
        headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
      });
      const data = await res.json();

      if (!res.ok) throw new Error(data.message || 'Preview failed');

      setHeaders(data.headers);
      setSamples(data.samples);
      setTotalRows(data.total_rows);

      const autoMapping: Record<string, string> = {};
      for (const field of systemFields) {
        const match = data.headers.find(
          (h: string) => h.toLowerCase().replace(/[^a-z]/g, '') === field.key.toLowerCase()
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
    formData.append('column_mapping', JSON.stringify(columnMapping));

    try {
      const res = await fetch(importUrl, {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
      });
      const data = await res.json();

      if (!res.ok) throw new Error(data.message || 'Import failed');

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
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">
              {step === 'upload' && 'Import Users'}
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
                  {systemFields.map((field) => (
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
                  disabled={loading || systemFields.some((f) => f.required && !columnMapping[f.key])}
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

File: `resources/js/Components/ImportExport/ImportModal.tsx`

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/ImportExport/ImportProgress.tsx resources/js/Components/ImportExport/ImportButton.tsx resources/js/Components/ImportExport/ImportModal.tsx
git commit -m "feat: add ImportButton, ImportModal, and ImportProgress components"
```

---

### Task 15: Create ExportButton and ExportModal

**Files:**
- Create: `resources/js/Components/ImportExport/ExportButton.tsx`
- (ExportModal is optional — simple export can skip the modal)

- [ ] **Step 1: Create ExportButton with dropdown**

```tsx
import React, { useState, useRef, useEffect } from 'react';
import Button from '../../Tailadmin/components/ui/button/Button';

interface ExportButtonProps {
  baseUrl: string;
}

export default function ExportButton({ baseUrl }: ExportButtonProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleExport = (format: string) => {
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('format', format);
    window.open(url.toString(), '_blank');
    setOpen(false);
  };

  return (
    <div ref={ref} className="relative inline-block">
      <Button variant="outline" onClick={() => setOpen(!open)}>
        Export ▾
      </Button>
      {open && (
        <div className="absolute right-0 mt-1 w-36 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50">
          <button
            className="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-lg"
            onClick={() => handleExport('xlsx')}
          >
            Excel (.xlsx)
          </button>
          <button
            className="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
            onClick={() => handleExport('csv')}
          >
            CSV
          </button>
          <button
            className="block w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 rounded-b-lg"
            onClick={() => handleExport('pdf')}
          >
            PDF
          </button>
        </div>
      )}
    </div>
  );
}
```

File: `resources/js/Components/ImportExport/ExportButton.tsx`

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/ImportExport/ExportButton.tsx
git commit -m "feat: add ExportButton with format dropdown"
```

---

### Task 16: Create ImportExportToolbar and wire up to Users page

**Files:**
- Create: `resources/js/Components/ImportExport/ImportExportToolbar.tsx`
- Modify: `resources/js/Pages/Users/Index.tsx`

- [ ] **Step 1: Create ImportExportToolbar**

```tsx
import React from 'react';
import ImportButton from './ImportButton';
import ExportButton from './ExportButton';

interface ImportExportToolbarProps {
  importUrl: string;
  previewUrl: string;
  exportUrl: string;
  onImportClick: () => void;
}

export default function ImportExportToolbar({ importUrl, previewUrl, exportUrl, onImportClick }: ImportExportToolbarProps) {
  return (
    <div className="flex items-center gap-3">
      <ImportButton onClick={onImportClick} />
      <ExportButton baseUrl={exportUrl} />
    </div>
  );
}
```

File: `resources/js/Components/ImportExport/ImportExportToolbar.tsx`

- [ ] **Step 2: Modify Users/Index.tsx to add toolbar + modal**

Add the import at the top of `resources/js/Pages/Users/Index.tsx`:

```tsx
import ImportExportToolbar from '../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../Components/ImportExport/ImportModal';
```

Add state at the top of the component body (after existing `useState` lines):

```tsx
const [importModalOpen, setImportModalOpen] = useState(false);
```

In the JSX, change the first `ComponentCard` section. Find this part:

```tsx
<div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div className="xl:col-span-1">
```

Replace it with:

```tsx
<div className="mb-4">
    <ImportExportToolbar
        importUrl={route('users.import')}
        previewUrl={route('users.import.preview')}
        exportUrl={route('users.export')}
        onImportClick={() => setImportModalOpen(true)}
    />
</div>

<ImportModal
    isOpen={importModalOpen}
    onClose={() => setImportModalOpen(false)}
    onComplete={() => {
        // Refresh the page to show new users
        window.location.reload();
    }}
    importUrl={route('users.import')}
    previewUrl={route('users.import.preview')}
/>

<div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div className="xl:col-span-1">
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/ImportExport/ImportExportToolbar.tsx resources/js/Pages/Users/Index.tsx
git commit -m "feat: wire ImportExportToolbar and ImportModal into Users page"
```

---

## Phase 7: Export Blade View & Notification

### Task 17: Create PDF export view and import notification

**Files:**
- Create: `resources/views/exports/table.blade.php`
- Create: `app/Notifications/ImportCompletedNotification.php`

- [ ] **Step 1: Create PDF export Blade view**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h2 { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; padding: 6px 8px; border-bottom: 2px solid #d1d5db; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

File: `resources/views/exports/table.blade.php`

- [ ] **Step 2: Create ImportCompletedNotification**

```php
<?php

namespace App\Notifications;

use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ImportCompletedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private readonly ImportLog $importLog) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Import of {$this->importLog->file_name} completed: {$this->importLog->processed_rows} rows imported, {$this->importLog->skipped_rows} skipped.",
            'import_log_id' => $this->importLog->id,
            'status' => $this->importLog->status,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
```

File: `app/Notifications/ImportCompletedNotification.php`

- [ ] **Step 3: Commit**

```bash
git add resources/views/exports/table.blade.php app/Notifications/ImportCompletedNotification.php
git commit -m "feat: add PDF export view and import completion notification"
```

---

## Phase 8: Tests

### Task 18: Write feature tests

**Files:**
- Create: `tests/Feature/ImportExport/ImportManagerTest.php`
- Create: `tests/Feature/ImportExport/ExportManagerTest.php`
- Create: `tests/Feature/ImportExport/ProcessImportTest.php`

- [ ] **Step 1: Create ImportManagerTest**

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\Imports\UserImporter;
use App\Services\ImportExport\Managers\ImportManager;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_headers_and_samples(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'test.xlsx',
            $this->fakeXlsxContent([['Email', 'Name', 'Password'], ['test@example.com', 'Test User', 'password123']])
        );

        $manager = app(ImportManager::class);
        $result = $manager->preview(new UserImporter(), $file);

        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('samples', $result);
        $this->assertArrayHasKey('total_rows', $result);
    }

    public function test_preview_rejects_invalid_format(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $this->expectException(\InvalidArgumentException::class);

        $manager = app(ImportManager::class);
        $manager->preview(new UserImporter(), $file);
    }

    public function test_start_creates_import_log_and_dispatches_job(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'users.xlsx',
            $this->fakeXlsxContent([['Email', 'Name', 'Password'], ['new@example.com', 'New User', 'password123']])
        );

        $manager = app(ImportManager::class);
        $importLog = $manager->start(new UserImporter(), $file, [
            'email' => 'Email',
            'name' => 'Name',
            'password' => 'Password',
        ], $user->id);

        $this->assertDatabaseHas('import_logs', ['id' => $importLog->id]);
        $this->assertEquals('pending', $importLog->status);
    }

    public function test_status_returns_import_log(): void
    {
        $user = User::factory()->create();
        $importLog = ImportLog::factory()->create(['user_id' => $user->id]);

        $manager = app(ImportManager::class);
        $result = $manager->status($importLog->id);

        $this->assertEquals($importLog->id, $result->id);
    }

    private function fakeXlsxContent(array $rows): string
    {
        // Create a simple CSV as fake content (tests pass through preview/parse)
        return implode("\n", array_map(fn ($r) => implode(',', $r), $rows));
    }
}
```

File: `tests/Feature/ImportExport/ImportManagerTest.php`

- [ ] **Step 2: Create ExportManagerTest**

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\UserExporter;
use App\Services\ImportExport\Managers\ExportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_export_downloads_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exporter = new UserExporter();
        $config = new ExportConfig(
            format: ExportFormat::Xlsx,
            fileName: 'test-export',
            headings: $exporter->headings(),
            columns: ['name', 'email', 'role', 'created_at'],
            exportableClass: UserExporter::class,
        );

        $manager = app(ExportManager::class);
        $response = $manager->download($exporter, $config);

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition') ?? '');
    }

    public function test_csv_export_downloads_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exporter = new UserExporter();
        $config = new ExportConfig(
            format: ExportFormat::Csv,
            fileName: 'test-export',
            headings: $exporter->headings(),
            columns: ['name', 'email', 'role', 'created_at'],
            exportableClass: UserExporter::class,
        );

        $manager = app(ExportManager::class);
        $response = $manager->download($exporter, $config);

        $this->assertEquals('text/csv', $response->headers->get('content-type'));
    }
}
```

File: `tests/Feature/ImportExport/ExportManagerTest.php`

- [ ] **Step 3: Create ProcessImportTest**

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\User;
use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Jobs\ProcessImport;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_imports_valid_rows(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'test.csv',
            "Email,Name,Password\nnew@example.com,New User,password123\nother@example.com,Other User,password456"
        );
        $path = $file->store('imports/test');

        $importLog = ImportLog::factory()->create([
            'user_id' => $user->id,
            'model_type' => User::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: User::class,
            columnMapping: ['email' => 'Email', 'name' => 'Name', 'password' => 'Password'],
            validationRules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
            uniqueKey: 'email',
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed->value, $importLog->status);
        $this->assertEquals(2, $importLog->processed_rows);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'other@example.com']);
    }

    public function test_job_skips_duplicates(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'existing@example.com']);

        $file = UploadedFile::fake()->createWithContent(
            'test.csv',
            "Email,Name,Password\nexisting@example.com,Existing,password123\nnew@example.com,New,password456"
        );
        $path = $file->store('imports/test');

        $importLog = ImportLog::factory()->create([
            'user_id' => $user->id,
            'model_type' => User::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: User::class,
            columnMapping: ['email' => 'Email', 'name' => 'Name', 'password' => 'Password'],
            validationRules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
            uniqueKey: 'email',
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed->value, $importLog->status);
        $this->assertEquals(1, $importLog->processed_rows);
        $this->assertEquals(1, $importLog->skipped_rows);
    }

    public function test_job_collects_validation_errors(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'test.csv',
            "Email,Name,Password\nbad-email,Valid Name,short"
        );
        $path = $file->store('imports/test');

        $importLog = ImportLog::factory()->create([
            'user_id' => $user->id,
            'model_type' => User::class,
            'status' => 'pending',
        ]);

        $config = new ImportConfig(
            format: ImportFormat::Csv,
            filePath: Storage::path($path),
            modelType: User::class,
            columnMapping: ['email' => 'Email', 'name' => 'Name', 'password' => 'Password'],
            validationRules: [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
            uniqueKey: 'email',
            chunkSize: 500,
        );

        (new ProcessImport($config, $importLog->id))->handle();

        $importLog->refresh();
        $this->assertEquals(ImportStatus::Completed->value, $importLog->status);
        $this->assertNotEmpty($importLog->errors);
    }
}
```

File: `tests/Feature/ImportExport/ProcessImportTest.php`

- [ ] **Step 4: Run tests and commit**

```bash
php artisan test --filter=ImportExport
git add tests/Feature/ImportExport/
git commit -m "test: add feature tests for ImportManager, ExportManager, and ProcessImport"
```

---

### Task 19: Write unit tests

**Files:**
- Create: `tests/Unit/ImportExport/ImportConfigTest.php`
- Create: `tests/Unit/ImportExport/EnumsTest.php`

- [ ] **Step 1: Create ImportConfig DTO test**

```php
<?php

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
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
        );

        $this->assertEquals(ImportFormat::Csv, $config->format);
        $this->assertEquals('/tmp/test.csv', $config->filePath);
        $this->assertEquals(500, $config->chunkSize);
        $this->assertEquals('email', $config->uniqueKey);
    }
}
```

File: `tests/Unit/ImportExport/ImportConfigTest.php`

- [ ] **Step 2: Create Enums test**

```php
<?php

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Enums\ImportStatus;
use Tests\TestCase;

class EnumsTest extends TestCase
{
    public function test_import_format_has_expected_cases(): void
    {
        $this->assertEquals('xlsx', ImportFormat::Xlsx->value);
        $this->assertEquals('csv', ImportFormat::Csv->value);
        $this->assertCount(2, ImportFormat::cases());
    }

    public function test_export_format_has_expected_cases(): void
    {
        $this->assertCount(3, ExportFormat::cases());
        $this->assertEquals('pdf', ExportFormat::Pdf->value);
    }

    public function test_import_status_has_expected_cases(): void
    {
        $cases = ImportStatus::cases();
        $this->assertCount(4, $cases);

        $values = array_map(fn ($c) => $c->value, $cases);
        $this->assertContains('pending', $values);
        $this->assertContains('processing', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('failed', $values);
    }
}
```

File: `tests/Unit/ImportExport/EnumsTest.php`

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --filter="ImportConfig|Enums"
git add tests/Unit/ImportExport/
git commit -m "test: add unit tests for ImportConfig DTO and enums"
```

---

## Final Verification

- [ ] **Run full test suite**

```bash
php artisan test
```

- [ ] **Verify the build compiles**

```bash
npm run build
```

- [ ] **Manual UI verification**

1. Start the dev server: `php artisan serve` + `npm run dev`
2. Log in, navigate to User Management
3. Verify Import and Export buttons are visible
4. Click Export → Excel — verify file downloads
5. Click Import → upload a CSV with columns Email, Name, Password
6. Verify column mapping step shows fields
7. Run import and verify progress bar
8. Check import_logs table for the record
