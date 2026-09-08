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

    /**
     * Import Excel besar (ribuan baris, grouping frame, validasi per-baris)
     * bisa berjalan >10 menit. Nilai ini menang atas flag --timeout worker
     * (lihat Worker::timeoutForJob), jadi job tidak di-kill di tengah jalan.
     */
    public $timeout = 1800;

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
        if (! empty($this->config->importerParams) && method_exists($importer, 'setContext')) {
            $importer->setContext($this->config->importerParams);
        }
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

        // Buang baris yang benar-benar kosong (sering ada di akhir sheet hasil
        // "save as" user) supaya tidak dihitung / tidak memicu error validasi.
        $dataRows = $rows->slice(1)->values()
            ->filter(function ($row) {
                return $row->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->isNotEmpty();
            })
            ->values();

        if ($dataRows->isEmpty()) {
            $importLog->update([
                'status' => ImportStatus::Completed->value,
                'total_rows' => 0,
            ]);
            return;
        }

        $importLog->update(['total_rows' => $dataRows->count()]);

        $chunks = $dataRows->chunk($this->config->chunkSize);

        $rowsSinceProgress = 0;
        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $rowIndex => $row) {
                $rowNumber = ($chunkIndex * $this->config->chunkSize) + $rowIndex + 1;
                $rowArray = $row->toArray();
                $mapped = $this->mapRow($rowArray, $headers);

                // Normalisasi nilai (mis. serial tanggal Excel → Y-m-d).
                $mapped = $importer->normalizeRowValues($mapped);
                $mapped = array_merge($mapped, $fixedFields);

                // Baris yang sengaja "0"/kosong bermakna dilewati (mis. qty 0) —
                // dihitung sebagai skipped, bukan error.
                if ($importer->shouldSkipRow($mapped)) {
                    $skipped++;
                    $rowsSinceProgress++;
                    if ($rowsSinceProgress >= 100) {
                        $this->reportProgress($importLog, $processed, $skipped);
                        $rowsSinceProgress = 0;
                    }
                    continue;
                }

                try {
                    $transformed = $importer->transformRow($mapped);
                } catch (RowTransformException $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'system',
                        'message' => $e->getMessage(),
                    ];
                    $rowsSinceProgress++;
                    if ($rowsSinceProgress >= 100) {
                        $this->reportProgress($importLog, $processed, $skipped);
                        $rowsSinceProgress = 0;
                    }
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
                    $rowsSinceProgress++;
                    if ($rowsSinceProgress >= 100) {
                        $this->reportProgress($importLog, $processed, $skipped);
                        $rowsSinceProgress = 0;
                    }
                    continue;
                }

                if ($importer->isDuplicate($transformed)) {
                    $skipped++;
                } else {
                    $importer->insertRow($transformed);
                    $processed++;
                }

                // Lapor progress rutin — UI polling (ImportProgress) membaca
                // processed_rows sehingga progress bar tidak terlihat beku
                // di "0/N rows" untuk file besar (mis. 3000+ baris).
                $rowsSinceProgress++;
                if ($rowsSinceProgress >= 100) {
                    $this->reportProgress($importLog, $processed, $skipped);
                    $rowsSinceProgress = 0;
                }
            }

            // Jaring pengaman: pastikan progress tersimpan di akhir tiap chunk
            $this->reportProgress($importLog, $processed, $skipped);
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

    /**
     * Simpan progress saat ini agar UI polling dapat menampilkan kemajuan
     * (processed/skipped bertambah) selama job masih berjalan — bukan hanya
     * di akhir proses.
     */
    private function reportProgress(ImportLog $importLog, int $processed, int $skipped): void
    {
        $importLog->update([
            'processed_rows' => $processed,
            'skipped_rows' => $skipped,
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
