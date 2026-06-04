<?php

namespace App\Services\ImportExport\Managers;

use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Enums\ImportStatus;
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
            'status' => ImportStatus::Pending->value,
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
