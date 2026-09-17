<?php

namespace App\Services\ImportExport\Managers;

use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Exceptions\ExportException;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportManager
{
    /**
     * Batas baris untuk format yang WAJIB dibangun penuh di memori
     * (xlsx = grid PhpSpreadsheet, pdf = DomPDF). CSV di-stream per halaman
     * sehingga tidak dibatasi.
     *
     * Tanpa batas ini, export seluruh riwayat bisa mematikan worker Octane
     * ("Allowed memory size ... exhausted") dan operator hanya melihat 502.
     */
    private const MAX_ROWS_IN_MEMORY = [
        'xlsx' => 20000,
        'pdf' => 3000,
    ];

    public function download(BaseExporter $exporter, ExportConfig $config): BinaryFileResponse|StreamedResponse|Response
    {
        $count = $exporter->exportQuery()->count();

        if ($count === 0) {
            throw new ExportException('Tidak ada data untuk diexport.');
        }

        $limit = self::MAX_ROWS_IN_MEMORY[$config->format->value] ?? null;

        if ($limit !== null && $count > $limit) {
            throw new ExportException(sprintf(
                'Data terlalu besar untuk format %s (%s baris, maksimal %s). Persempit rentang tanggal, atau pilih format CSV yang tidak dibatasi.',
                strtoupper($config->format->value),
                number_format($count, 0, ',', '.'),
                number_format($limit, 0, ',', '.'),
            ));
        }

        return $exporter->download($config);
    }
}
