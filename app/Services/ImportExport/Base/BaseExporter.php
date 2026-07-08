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
