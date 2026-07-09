<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Managers\ExportManager;
use App\Services\ImportExport\Managers\ImportManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HasImportExport
{
    abstract protected function importer(): BaseImporter;

    abstract protected function exporter(): BaseExporter;

    abstract protected function exportFileName(): string;

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = app(ImportManager::class)->preview($this->importer(), $request->file('file'));

        return response()->json($result);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $importLog = app(ImportManager::class)->start(
            $this->importer(),
            $request->file('file'),
            $request->input('column_mapping'),
            Auth::id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function export(Request $request)
    {
        return $this->performExport($request);
    }

    protected function performExport(Request $request)
    {
        $format = ExportFormat::from($request->query('format', 'xlsx'));
        $exporter = $this->exporter();

        $config = new ExportConfig(
            format: $format,
            fileName: $this->exportFileName() . '-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: [],
            exportableClass: $exporter::class,
        );

        return app(ExportManager::class)->download($exporter, $config);
    }

    public function importTemplate(Request $request)
    {
        $format = ImportFormat::from($request->query('format', 'xlsx'));

        return $this->importer()->downloadTemplate($format);
    }
}
