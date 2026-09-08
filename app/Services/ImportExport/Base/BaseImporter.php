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

    /**
     * Baris yang harus dilewati secara diam-diam (dihitung sebagai "skipped"),
     * DIPERIKSA SEBELUM transformRow()/validasi — mis. quantity = 0 pada importer
     * tertentu yang bermakna "tidak ada pesanan". Return false = proses normal.
     */
    public function shouldSkipRow(array $mapped): bool
    {
        return false;
    }

    /**
     * Normalisasi nilai per baris hasil mapping sebelum transformRow/validasi.
     * Saat ini dipakai untuk kolom bertipe "date": mengubah serial tanggal Excel
     * (mis. 46273) dan format tanggal teks umum menjadi Y-m-d.
     */
    public function normalizeRowValues(array $mapped): array
    {
        foreach ($this->rules() as $key => $rule) {
            if (! $this->ruleUsesDate($rule)) {
                continue;
            }

            $value = $mapped[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $mapped[$key] = $this->normalizeDateValue($value);
        }

        return $mapped;
    }

    private function ruleUsesDate(array|string $rule): bool
    {
        $flat = is_array($rule) ? implode('|', $rule) : $rule;

        return str_contains($flat, 'date');
    }

    private function normalizeDateValue(mixed $value): mixed
    {
        // Serial tanggal Excel dibaca sebagai angka (mis. 46273 = 2026-09-08)
        // karena pembacaan memakai raw values, bukan format tampilan.
        if (is_numeric($value)) {
            $n = (float) $value;
            if ($n >= 20000 && $n <= 80000) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) round($n))->format('Y-m-d');
            }
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value)) {
            return $value;
        }

        $raw = trim($value);
        if ($raw === '') {
            return $value;
        }

        if (is_numeric($raw)) {
            $n = (float) $raw;
            if ($n >= 20000 && $n <= 80000) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) round($n))->format('Y-m-d');
            }
        }

        // Format tanggal teks umum (Excel/CSV lintas locale): d/m/Y, m/d/Y, Y-m-d, ...
        if (preg_match('/^\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}$/', $raw)) {
            foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y', 'Y-m-d', 'Y/m/d', 'Y-m-d H:i:s', 'd/m/Y H:i:s'] as $format) {
                $dt = \DateTimeImmutable::createFromFormat($format, $raw);
                if ($dt && $dt->format($format) === $raw) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false && preg_match('/\d{4}/', $raw)) {
            return date('Y-m-d', $ts);
        }

        return $value;
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

    /** File name (without extension) for the downloadable template, derived from the importer class name. */
    public function templateFileName(): string
    {
        return 'import-template-' . Str::kebab(Str::replaceLast('Importer', '', class_basename(static::class)));
    }

    /** Extra context carried through the import config to the (reconstructed) importer in the job. */
    public function contextParams(): array
    {
        return [];
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

        return Excel::download($export, $this->templateFileName() . '.xlsx');
    }

    private function downloadTemplateCsv(array $headings): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->templateFileName() . '.csv"');

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
            importerParams: $this->contextParams(),
        );
    }
}
