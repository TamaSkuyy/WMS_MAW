<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;

/**
 * Import cycle dari file template flat:
 *   Cycle Number | Supplier | Delivery Date | Part Number | Quantity | Notes
 *
 * Penyesuaian agar mendukung file yang dibuat user/supplier:
 * - Kolom "Supplier" boleh berisi NAMA ataupun KODE supplier.
 * - Delivery Date otomatis dinormalisasi (serial tanggal Excel / format teks)
 *   oleh BaseImporter::normalizeRowValues() sebelum validasi.
 * - Quantity = 0 dilewati (bermakna "tidak ada pesanan" pada file data order),
 *   dihitung sebagai skipped bukan error.
 * - Pengelompokan cycle per (supplier × tanggal × cycle_number di file).
 * - AUTO-NUMBER per hari: nomor cycle yang disimpan = nomor gelombang HARI ITU
 *   per supplier (mulai 1 tiap tanggal, unik per supplier+tanggal). Nomor di
 *   file dipakai bila masih bebas; bila sudah terpakai dipakai nomor bebas
 *   berikutnya. File harian berisi "1" bisa dipakai berulang tiap hari tanpa
 *   tabrakan.
 */
class CycleImporter extends BaseImporter implements Importable
{
    /** "supplierId|tanggal|cycle-file" => Cycle yang sedang dibangun. */
    private array $currentCycle = [];

    /** "supplierId|tanggal" => set nomor cycle yg sudah terpakai/telah dialokasikan. */
    private array $usedNumbers = [];

    public function modelType(): string
    {
        return Cycle::class;
    }

    public function uniqueKey(): string|array
    {
        return 'cycle_number';
    }

    public function rules(): array
    {
        return [
            'cycle_number' => ['required', 'string', 'max:50'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'delivery_date' => ['required', 'date'],
            'part_number' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Cycle Number', 'Supplier', 'Delivery Date', 'Part Number', 'Quantity', 'Notes'];
    }

    /**
     * Qty 0 pada file data order berarti "tidak ada pesanan" → lewati baris
     * dengan tenang (dihitung skipped) sebelum validasi/transform.
     */
    public function shouldSkipRow(array $mapped): bool
    {
        if (! array_key_exists('quantity', $mapped)) {
            return false;
        }

        $qty = $mapped['quantity'];

        return $qty !== null && $qty !== '' && (int) $qty === 0;
    }

    public function transformRow(array $mapped): array
    {
        // Supplier boleh ditulis sebagai NAMA atau KODE di file.
        $supplierRef = trim((string) ($mapped['supplier_name'] ?? ''));
        $supplier = null;
        if ($supplierRef !== '') {
            $supplier = Supplier::query()
                ->where('code', $supplierRef)
                ->orWhere('name', $supplierRef)
                ->first();
        }

        if (! $supplier) {
            throw new RowTransformException(
                'Supplier dengan kode/nama "' . $supplierRef . '" tidak ditemukan.'
            );
        }
        $mapped['supplier_id'] = $supplier->id;

        // Resolve product_id dari part_number
        $partNumber = trim((string) ($mapped['part_number'] ?? ''));
        $product = Product::where('part_number', $partNumber)->first();
        if (! $product) {
            throw new RowTransformException(
                "Product dengan part_number \"{$partNumber}\" tidak ditemukan."
            );
        }
        $mapped['product_id'] = $product->id;

        return $mapped;
    }

    public function fixedFields(int $userId): array
    {
        return [
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    /**
     * Nomor cycle dikelola otomatis per (supplier × delivery_date) — reset ke 1
     * setiap tanggal baru — jadi tidak ada baris yang di-skip karena nomor di
     * file sudah pernah dipakai.
     */
    public function isDuplicate(array $data): bool
    {
        return false;
    }

    public function insertRow(array $data): void
    {
        $supplierId = (int) $data['supplier_id'];
        $fileCycle = (string) $data['cycle_number'];
        $date = $data['delivery_date'] ?? null;
        $dateKey = $date ?: '(tanpa-tanggal)';
        $groupKey = $supplierId . '|' . $dateKey . '|' . $fileCycle;

        // Kelompok baru (supplier × tanggal × nomor file) → cycle baru.
        if (! isset($this->currentCycle[$groupKey])) {
            $usedKey = $supplierId . '|' . $dateKey;
            if (! isset($this->usedNumbers[$usedKey])) {
                $this->usedNumbers[$usedKey] = Cycle::where('supplier_id', $supplierId)
                    ->when($date, fn ($q) => $q->whereDate('delivery_date', $date), fn ($q) => $q->whereNull('delivery_date'))
                    ->pluck('cycle_number')
                    ->flip()
                    ->all();
            }

            $this->currentCycle[$groupKey] = Cycle::create([
                'supplier_id' => $supplierId,
                'cycle_number' => $this->allocateNumber($this->usedNumbers[$usedKey], (int) $fileCycle),
                'delivery_date' => $date,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['updated_by'] ?? null,
            ]);
        }

        // Tambah item ke cycle tsb.
        CycleItem::create([
            'cycle_id' => $this->currentCycle[$groupKey]->id,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'received_quantity' => 0,
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ]);
    }

    /**
     * Nomor cycle utk hari itu: pakai nomor file bila bebas, kalau tidak ambil
     * nomor bebas terkecil (≥1).
     *
     * @param  array<int, true>  $used  (by-ref) set nomor yg terpakai tanggal tsb
     */
    private function allocateNumber(array &$used, int $prefer): int
    {
        if ($prefer >= 1 && ! isset($used[$prefer])) {
            $used[$prefer] = true;

            return $prefer;
        }

        $n = 1;
        while (isset($used[$n])) {
            $n++;
        }
        $used[$n] = true;

        return $n;
    }
}
