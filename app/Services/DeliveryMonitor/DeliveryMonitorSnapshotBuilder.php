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

        // A supplier's "relevant" slots for the monitor = their configured
        // recurring schedule UNION any slot they actually have a real cycle
        // in today. Without the union, an ad-hoc receive from a supplier
        // nobody has gotten around to scheduling yet would be invisible even
        // though the receiving genuinely happened.
        $relevantSlotIdsBySupplier = [];
        foreach ($suppliers as $supplier) {
            $relevantSlotIdsBySupplier[$supplier->id] = $supplier->scheduledSlots->pluck('id');
        }
        foreach ($cycles as $cycle) {
            $relevantSlotIdsBySupplier[$cycle->supplier_id] = ($relevantSlotIdsBySupplier[$cycle->supplier_id] ?? collect())
                ->push($cycle->delivery_slot_id)
                ->unique();
        }

        return [
            'suppliers' => $suppliers->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $this->computeSupplierStatus($supplier, $slots, $cyclesBySupplierSlot, $relevantSlotIdsBySupplier[$supplier->id]),
            ])->values()->all(),

            'slots' => $slots->map(fn (DeliverySlot $slot) => [
                'cycleNumber' => $slot->slot_number,
                'timeStart' => substr($slot->time_start, 0, 5),
                'timeEnd' => substr($slot->time_end, 0, 5),
            ])->values()->all(),

            'cycles' => $this->buildDeliveryCycles($suppliers, $slots, $cyclesBySupplierSlot, $relevantSlotIdsBySupplier),

            'parts' => $this->buildParts($suppliers, $relevantSlotIdsBySupplier, $cycles),

            'receipts' => $this->buildReceipts($cycles),
        ];
    }

    private function computeSupplierStatus(Supplier $supplier, Collection $slots, Collection $cyclesBySupplierSlot, Collection $relevantSlotIds): string
    {
        if ($relevantSlotIds->isEmpty()) {
            return 'standby';
        }

        $now = now();
        $anyIncomplete = false;
        $anyOverdue = false;

        foreach ($slots as $slot) {
            if (! $relevantSlotIds->contains($slot->id)) {
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

    private function buildDeliveryCycles(Collection $suppliers, Collection $slots, Collection $cyclesBySupplierSlot, array $relevantSlotIdsBySupplier): array
    {
        $result = [];

        foreach ($suppliers as $supplier) {
            $relevantSlotIds = $relevantSlotIdsBySupplier[$supplier->id];

            foreach ($slots as $slot) {
                if (! $relevantSlotIds->contains($slot->id)) {
                    continue;
                }

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

    private function buildParts(Collection $suppliers, array $relevantSlotIdsBySupplier, Collection $cycles): array
    {
        $relevantSupplierIds = $suppliers
            ->filter(fn (Supplier $supplier) => $relevantSlotIdsBySupplier[$supplier->id]->isNotEmpty())
            ->pluck('id');

        // Start from the master catalog (products whose own supplier_id is
        // relevant) ...
        $partsById = [];
        foreach (Product::whereIn('supplier_id', $relevantSupplierIds)->with('category')->get() as $product) {
            $partsById[$product->id] = $this->mapPart($product, $product->supplier_id);
        }

        // ... then union in anything actually received today under a
        // relevant supplier's cycle, even if that product's own catalog
        // supplier_id points elsewhere — the receiving forms let staff pick
        // any product for any supplier, so a part can legitimately show up
        // under a supplier other than its catalog default. Tag it with the
        // cycle's supplier since that reflects who is actually delivering it
        // today, which is what the monitor is tracking.
        foreach ($cycles as $cycle) {
            if (! $relevantSupplierIds->contains($cycle->supplier_id)) {
                continue;
            }

            foreach ($cycle->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $partsById[$item->product->id] = $this->mapPart($item->product, $cycle->supplier_id);
            }
        }

        return array_values($partsById);
    }

    private function mapPart(Product $product, int $supplierId): array
    {
        return [
            'id' => $product->id,
            'partNumber' => $product->part_number,
            'partName' => $product->name,
            'category' => $product->category->name ?? 'Uncategorized',
            'supplierId' => $supplierId,
        ];
    }

    private function buildReceipts(Collection $cycles): array
    {
        $receipts = [];

        foreach ($cycles as $cycle) {
            foreach ($cycle->items as $item) {
                $receipts[] = [
                    'id' => $item->id,
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
