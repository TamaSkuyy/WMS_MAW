<?php

namespace App\Services\ImportExport\Imports;

/**
 * Import BARANG untuk shopping (langkah 2 dari alur 2 langkah).
 *
 * File berisi Frame Number + Part Number + Quantity (opsional Confirmed /
 * Cripple / Modify Date). Frame harus sudah terdaftar di WMS — hasil input
 * header (line & frame number) di halaman "Input Header Frame".
 *
 * Operator tetap bisa memilih "Buat frame otomatis" di UI kalau pusat/TAM
 * mengubah alur dan mengirim file barang tanpa input header lebih dulu.
 */
class ShoppingItemImporter extends ShoppingImporter
{
    public function __construct(?int $shoppingLocationId = null, bool $autoCreateFrame = false)
    {
        parent::__construct(
            shoppingLocationId: $shoppingLocationId,
            requireExistingFrame: true,
            autoCreateFrame: $autoCreateFrame,
        );
    }

    /** Template ramping: kolom yang benar-benar dipakai alur import barang. */
    public function templateHeadings(): array
    {
        return ['Frame Number', 'Part Number', 'Quantity'];
    }
}
