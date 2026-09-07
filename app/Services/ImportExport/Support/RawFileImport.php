<?php

namespace App\Services\ImportExport\Support;

use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Baca file excel/csv apa adanya (tanpa transformasi baris).
 *
 * Delimiter CSV sengaja dikembalikan null (= default config/excel.php) supaya
 * PhpSpreadsheet mendeteksi otomatis — koma, titik-koma, maupun TAB (hasil
 * "save as" dari Excel/Google Sheets sering memakai TAB). Memaksa koma di sini
 * membuat file TSV terbaca sebagai 1 kolom utuh sehingga header tidak cocok.
 */
class RawFileImport implements WithCustomCsvSettings
{
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => null, // auto-detect
        ];
    }
}
