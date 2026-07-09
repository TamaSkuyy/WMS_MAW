<?php

namespace App\Services\ImportExport\Base;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Exceptions\RowTransformException;
use App\Services\ImportExport\Support\RawFileImport;
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

    public function downloadTemplate(ImportFormat $format): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headings = $this->templateHeadings();

        return match ($format) {
            ImportFormat::Xlsx => $this->downloadTemplateXlsx($headings),
            ImportFormat::Csv => $this->downloadTemplateCsv($headings),
        };
    }

    private function downloadTemplateXlsx(array $headings): \Symfony\Component\HttpFoundation\BinaryFileResponse
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

    private function downloadTemplateCsv(array $headings): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="import-template.csv"');

        return $response;
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

        $rows = Excel::toCollection(new RawFileImport(), Storage::path($path))->first();

        $headers = $rows && $rows->isNotEmpty()
            ? $rows->first()->toArray()
            : [];
        $samples = $rows
            ? $rows->slice(1)->take(3)->map(fn ($r) => $r->toArray())->values()->toArray()
            : [];
        $totalRows = $rows ? max($rows->count() - 1, 0) : 0;

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
