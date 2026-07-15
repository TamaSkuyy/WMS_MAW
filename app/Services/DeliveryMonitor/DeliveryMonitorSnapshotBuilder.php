<?php

namespace App\Services\DeliveryMonitor;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeliveryMonitorSnapshotBuilder
{
    public function build(Carbon $date): array
    {
        $slots = DeliverySlot::orderBy('slot_number')->get();
        $suppliers = Supplier::with('scheduledSlots')->orderBy('name')->get();

        $cycles = Cycle::whereDate('delivery_date', $date)
            ->whereNotNull('delivery_slot_id')
            ->with(['items.product', 'deliverySlot'])
            ->get();

        $cyclesBySupplierSlot = $cycles->groupBy(fn (Cycle $cycle) => $cycle->supplier_id . ':' . $cycle->delivery_slot_id);

        return [
            'suppliers' => $suppliers->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $this->computeSupplierStatus($supplier, $slots, $cyclesBySupplierSlot),
            ])->values()->all(),

            'slots' => $slots->map(fn (DeliverySlot $slot) => [
                'cycleNumber' => $slot->slot_number,
                'timeStart' => substr($slot->time_start, 0, 5),
                'timeEnd' => substr($slot->time_end, 0, 5),
            ])->values()->all(),

            'cycles' => $this->buildDeliveryCycles($suppliers, $cyclesBySupplierSlot),

            'parts' => $this->buildParts($suppliers),

            'receipts' => $this->buildReceipts($cycles),
        ];
    }

    private function computeSupplierStatus(Supplier $supplier, Collection $slots, Collection $cyclesBySupplierSlot): string
    {
        $scheduledSlotIds = $supplier->scheduledSlots->pluck('id');

        if ($scheduledSlotIds->isEmpty()) {
            return 'standby';
        }

        $now = now();
        $anyIncomplete = false;
        $anyOverdue = false;

        foreach ($slots as $slot) {
            if (! $scheduledSlotIds->contains($slot->id)) {
                continue;
            }

            $slotCycles = $cyclesBySupplierSlot->get($supplier->id . ':' . $slot->id, collect());
            $plan = $slotCycles->flatMap->items->sum('quantity');
            $actual = $slotCycles->flatMap->items->sum('received_quantity');
            $isComplete = $plan > 0 && $actual >= $plan;

            if (! $isComplete) {
                $anyIncomplete = true;

                if ($now->format('H:i:s') >= $slot->time_end) {
                    $anyOverdue = true;
                }
            }
        }

        if (! $anyIncomplete) {
            return 'done';
        }

        return $anyOverdue ? 'alert' : 'live';
    }

    private function buildDeliveryCycles(Collection $suppliers, Collection $cyclesBySupplierSlot): array
    {
        $result = [];

        foreach ($suppliers as $supplier) {
            foreach ($supplier->scheduledSlots as $slot) {
                $slotCycles = $cyclesBySupplierSlot->get($supplier->id . ':' . $slot->id, collect());

                $result[] = [
                    'supplierId' => $supplier->id,
                    'cycleNumber' => $slot->slot_number,
                    'timeStart' => substr($slot->time_start, 0, 5),
                    'timeEnd' => substr($slot->time_end, 0, 5),
                    'planQty' => (int) $slotCycles->flatMap->items->sum('quantity'),
                    'actualQty' => (int) $slotCycles->flatMap->items->sum('received_quantity'),
                ];
            }
        }

        return $result;
    }

    private function buildParts(Collection $suppliers): array
    {
        $scheduledSupplierIds = $suppliers
            ->filter(fn (Supplier $supplier) => $supplier->scheduledSlots->isNotEmpty())
            ->pluck('id');

        return Product::whereIn('supplier_id', $scheduledSupplierIds)
            ->with('category')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'partNumber' => $product->part_number,
                'partName' => $product->name,
                'category' => $product->category->name ?? 'Uncategorized',
                'supplierId' => $product->supplier_id,
            ])->values()->all();
    }

    private function buildReceipts(Collection $cycles): array
    {
        $receipts = [];

        foreach ($cycles as $cycle) {
            foreach ($cycle->items as $item) {
                $receipts[] = [
                    'partId' => $item->product_id,
                    'cycleNumber' => $cycle->deliverySlot->slot_number,
                    'planQty' => (int) $item->quantity,
                    'receivedQty' => (int) $item->received_quantity,
                    'status' => $this->computeReceiptStatus((int) $item->quantity, (int) $item->received_quantity),
                ];
            }
        }

        return $receipts;
    }

    private function computeReceiptStatus(int $plan, int $received): string
    {
        if ($received === 0 && $plan > 0) {
            return 'pending';
        }

        if ($received < $plan) {
            return 'shortage';
        }

        if ($received > $plan) {
            return 'over';
        }

        return 'matched';
    }
}
