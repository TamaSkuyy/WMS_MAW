<?php

namespace App\Services\ImportExport\Jobs;

use App\Services\ImportExport\DTOs\ImportConfig;
use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Exceptions\RowTransformException;
use App\Services\ImportExport\Models\ImportLog;
use App\Services\ImportExport\Support\RawFileImport;
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

        $rows = Excel::toCollection(new RawFileImport(), $this->config->filePath)->first();

        if ($rows->isEmpty()) {
            $importLog->update([
                'status' => ImportStatus::Completed->value,
                'total_rows' => 0,
            ]);
            return;
        }

        $headers = $rows->first()->toArray();
        $dataRows = $rows->slice(1)->values();

        if ($dataRows->isEmpty()) {
            $importLog->update([
                'status' => ImportStatus::Completed->value,
                'total_rows' => 0,
            ]);
            return;
        }

        $importLog->update(['total_rows' => $dataRows->count()]);

        $chunks = $dataRows->chunk($this->config->chunkSize);

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
                $value = $row[$fileColumn] ?? $row[$index] ?? null;
                $mapped[$field] = (is_int($value) || is_float($value)) ? (string) $value : $value;
            }
        }
        return $mapped;
    }
}
