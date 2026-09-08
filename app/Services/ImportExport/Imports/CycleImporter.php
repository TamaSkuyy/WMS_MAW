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
 * - Pengelompokan cycle per (supplier × cycle_number di file), bukan hanya
 *   cycle_number — mencegah item supplier lain nyasar ke cycle supplier
 *   pertama saat satu file memuat banyak supplier.
 * - AUTO-RENUMBER: angka Cycle Number di file hanya dipakai sebagai urutan
 *   kelompok. Nomor cycle yang disimpan = max(cycle_number supplier) + 1
 *   terus bertambah (sama seperti alur buat cycle manual / Import Data
 *   Order). Ini memungkinkan file harian berisi "1" dipakai berulang tanpa
 *   tabrakan dengan nomor cycle yang sudah ada.
 */
class CycleImporter extends BaseImporter implements Importable
{
    /** supplierId => cycle_number (di file) yang sedang dibangun. */
    private array $currentFileCycleBySupplier = [];

    /** supplierId => nomor cycle DB berikutnya yang akan dipakai. */
    private array $nextNumberBySupplier = [];

    /** supplierId => Cycle yang sedang dibangun. */
    private array $currentCycleBySupplier = [];

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
     * Nomor cycle dikelola otomatis (max+1 per supplier), jadi tidak ada
     * baris yang di-skip karena nomor di file sudah pernah dipakai.
     */
    public function isDuplicate(array $data): bool
    {
        return false;
    }

    public function insertRow(array $data): void
    {
        $supplierId = (int) $data['supplier_id'];
        $fileCycle = (string) $data['cycle_number'];

        // Kelompok baru (supplier × nomor cycle di file) → cycle baru bernomor otomatis.
        if (($this->currentFileCycleBySupplier[$supplierId] ?? null) !== $fileCycle) {
            if (! isset($this->nextNumberBySupplier[$supplierId])) {
                $this->nextNumberBySupplier[$supplierId] = (int) Cycle::where('supplier_id', $supplierId)->max('cycle_number') + 1;
            }

            $this->currentCycleBySupplier[$supplierId] = Cycle::create([
                'supplier_id' => $supplierId,
                'cycle_number' => $this->nextNumberBySupplier[$supplierId]++,
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['updated_by'] ?? null,
            ]);
            $this->currentFileCycleBySupplier[$supplierId] = $fileCycle;
        }

        // Tambah item ke cycle supplier tsb.
        CycleItem::create([
            'cycle_id' => $this->currentCycleBySupplier[$supplierId]->id,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'received_quantity' => 0,
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ]);
    }
}
