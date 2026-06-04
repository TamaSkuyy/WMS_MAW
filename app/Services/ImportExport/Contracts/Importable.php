<?php

namespace App\Services\ImportExport\Contracts;

interface Importable
{
    /** @return array<string, array<int, string>> Laravel validation rules keyed by column */
    public function rules(): array;

    /** Column used to detect duplicate rows (e.g. 'email') */
    public function uniqueKey(): string;

    /** Rows to process per queue chunk */
    public function chunkSize(): int;
}
