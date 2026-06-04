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
            chunkSize: $this->chunkSize(),
        );
    }
}
