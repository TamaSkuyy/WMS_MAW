<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Dukungan "baris per halaman" untuk semua daftar (index) di aplikasi.
 *
 * Pemakaian: ->paginate($this->perPage(10))  // 10 = default lama controller
 * Query string yang dikenali: ?per_page=10|25|50|100 (nilai lain diabaikan).
 */
trait HasPagination
{
    /** Pilihan jumlah baris per halaman yang disediakan UI. */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    protected function perPage(int $default = 10, ?Request $request = null): int
    {
        $request ??= request();

        $value = (int) $request->integer('per_page');

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $default;
    }
}
