<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;

/**
 * Import HEADER shopping (langkah 1 dari alur 2 langkah): Line + Frame Number.
 *
 * Menghasilkan shopping DRAFT tanpa item — item diisi pada langkah berikutnya
 * lewat {@see ShoppingItemImporter}. Disiapkan untuk dipakai bila pihak
 * pusat/TAM memutuskan kirim file header; tombolnya masih disembunyikan di UI
 * (lihat SHOW_HEADER_IMPORT di halaman Input Header Frame).
 */
class ShoppingHeaderImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Shopping::class;
    }

    public function uniqueKey(): string|array
    {
        return 'frame_number';
    }

    public function rules(): array
    {
        return [
            'line' => ['nullable', 'string', 'max:255'],
            'frame_number' => ['required', 'string', 'max:100'],
            'is_cripple' => ['nullable'],
            'modify_date' => ['nullable', 'date'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Line', 'Frame Number', 'Cripple', 'Modify Date'];
    }

    public function fixedFields(int $userId): array
    {
        return [
            'status' => 'draft',
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    /** Baris tanpa frame number = baris kosong/tidak dipakai → dilewati. */
    public function shouldSkipRow(array $mapped): bool
    {
        return trim((string) ($mapped['frame_number'] ?? '')) === '';
    }

    public function transformRow(array $mapped): array
    {
        $mapped['frame_number'] = trim((string) $mapped['frame_number']);

        // Line = nama ATAU barcode lokasi (TAM kadang kirim kode).
        $line = trim((string) ($mapped['line'] ?? ''));
        $mapped['shopping_location_id'] = $line === '' ? null : $this->resolveLocation($line);

        if (isset($mapped['is_cripple'])) {
            $raw = $mapped['is_cripple'];
            $mapped['is_cripple'] = is_bool($raw)
                ? $raw
                : in_array(strtolower(trim((string) $raw)), ['yes', 'ya', 'y', 'true', '1'], true);
        }

        // Tanggal: pakai Modify Date kalau ada, kalau tidak hari ini.
        $mapped['shopping_date'] = now();
        $rawDate = $mapped['modify_date'] ?? null;
        if ($rawDate) {
            try {
                $mapped['shopping_date'] = \Illuminate\Support\Carbon::parse($rawDate);
            } catch (\Throwable) {
                // tanggal tak terbaca → hari ini
            }
        }

        unset($mapped['modify_date'], $mapped['line']);

        return $mapped;
    }

    private function resolveLocation(string $line): int
    {
        $id = ShoppingLocation::where('name', $line)->value('id')
            ?? ShoppingLocation::where('barcode', $line)->value('id');

        if ($id === null) {
            throw new RowTransformException(
                "Line/Lokasi \"{$line}\" tidak ditemukan. Daftarkan dulu di master Shopping Location (nama atau barcode harus sama)."
            );
        }

        return (int) $id;
    }
}
