<?php

namespace App\Services\ImportExport\Exceptions;

use RuntimeException;

/**
 * Export tidak bisa dijalankan karena alasan yang bisa dimengerti operator
 * (mis. tidak ada data, atau data terlalu besar untuk format xlsx/pdf).
 *
 * Ditangkap di controller → pesan ditampilkan sebagai flash "error",
 * bukan halaman 500.
 */
class ExportException extends RuntimeException
{
}
