<?php

namespace App\Services\ImportExport\Base;

use App\Services\ImportExport\Contracts\Exportable;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseExporter implements Exportable
{
    /** Baris per halaman saat streaming CSV. */
    private const EXPORT_CHUNK = 500;

    abstract public function headings(): array;

    abstract public function exportQuery(): \Illuminate\Database\Eloquent\Builder;

    abstract public function mapRow($model): array;

    public function download(ExportConfig $config): BinaryFileResponse|StreamedResponse|Response
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

        $export = new class($rows, $config->headings) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        return Excel::download($export, $config->fileName . '.xlsx');
    }

    private function downloadCsv(ExportConfig $config): StreamedResponse
    {
        // Stream per halaman (500 baris). Sebelumnya seluruh hasil di-`get()`
        // dulu — model + array sekaligus — sehingga export riwayat besar
        // menghabiskan memory_limit. CSV tidak butuh grid di memori, jadi
        // format ini aman untuk data besar.
        $response = new StreamedResponse(function () use ($config) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $config->headings);

            $page = 1;
            do {
                $models = $this->exportQuery()->forPage($page, self::EXPORT_CHUNK)->get();

                foreach ($models as $model) {
                    fputcsv($handle, $this->mapRow($model));
                }

                $page++;
            } while ($models->count() === self::EXPORT_CHUNK);

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $config->fileName . '.csv"');

        return $response;
    }

    private function downloadPdf(ExportConfig $config): Response
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
