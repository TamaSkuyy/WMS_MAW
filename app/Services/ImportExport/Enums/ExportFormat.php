<?php

namespace App\Services\ImportExport\Enums;

enum ExportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case Pdf = 'pdf';
}
