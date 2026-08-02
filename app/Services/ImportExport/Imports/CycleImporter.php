<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;
use App\Services\ImportExport\Exceptions\RowTransformException;

class CycleImporter extends BaseImporter implements Importable
{
    private ?string $currentCycleNumber = null;
    private ?Cycle $currentCycle = null;

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

    public function transformRow(array $mapped): array
    {
        // Resolve supplier_id from supplier name
        $supplierId = $this->resolveForeignKey(Supplier::class, 'name', $mapped['supplier_name'] ?? null, true);
        $mapped['supplier_id'] = $supplierId;

        // Resolve product_id from part_number
        $productId = $this->resolveForeignKey(Product::class, 'part_number', $mapped['part_number'] ?? null, true);
        $mapped['product_id'] = $productId;

        return $mapped;
    }

    public function isDuplicate(array $data): bool
    {
        $cycleNumber = $data['cycle_number'];

        // Still building the same cycle — allow insertRow to add more items
        if ($cycleNumber === $this->currentCycleNumber) {
            return false;
        }

        // Already exists in database — skip
        if (Cycle::where('cycle_number', $cycleNumber)->exists()) {
            return true;
        }

        return false;
    }

    public function insertRow(array $data): void
    {
        $cycleNumber = $data['cycle_number'];

        // New cycle — create header
        if ($cycleNumber !== $this->currentCycleNumber) {
            $this->currentCycle = Cycle::create([
                'cycle_number' => $cycleNumber,
                'supplier_id' => $data['supplier_id'],
                'delivery_date' => $data['delivery_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);
            $this->currentCycleNumber = $cycleNumber;
        }

        // Add item to current cycle
        CycleItem::create([
            'cycle_id' => $this->currentCycle->id,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'received_quantity' => 0,
        ]);
    }
}
