<?php

namespace App\Services\ImportExport\Enums;

enum ImportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';
}
