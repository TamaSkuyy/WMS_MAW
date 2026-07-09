<?php

namespace App\Services\ImportExport\Contracts;

interface Importable
{
    /** @return array<string, array<int, string>> Laravel validation rules keyed by column */
    public function rules(): array;

    /** Column(s) used to detect duplicate rows (e.g. 'email', or ['name', 'brand'] for composite uniqueness) */
    public function uniqueKey(): string|array;

    /** Rows to process per queue chunk */
    public function chunkSize(): int;
}
