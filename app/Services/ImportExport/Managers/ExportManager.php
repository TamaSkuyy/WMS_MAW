<?php

namespace App\Services\ImportExport\Managers;

use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\DTOs\ExportConfig;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportManager
{
    public function download(BaseExporter $exporter, ExportConfig $config): BinaryFileResponse|StreamedResponse|Response
    {
        $count = $exporter->exportQuery()->count();

        if ($count === 0) {
            throw new \RuntimeException('No data to export.');
        }

        return $exporter->download($config);
    }
}
