<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Supplier;
use App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryMonitorController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->resolveDate($request);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build($date);

        return Inertia::render('DeliveryMonitor/Index', array_merge($snapshot, [
            'selectedDate' => $date->toDateString(),
        ]));
    }

    /**
     * Historical cycle ledger for one supplier — newest first, paginated.
     */
    public function ledger(Supplier $supplier)
    {
        $cycles = Cycle::where('supplier_id', $supplier->id)
            ->with(['items', 'deliverySlot'])
            ->orderByDesc('delivery_date')
            ->orderByDesc('created_at')
            ->paginate(15);

        $cycles->getCollection()->transform(fn (Cycle $cycle) => [
            'id' => $cycle->id,
            'cycleNumber' => $cycle->cycle_number,
            'deliveryDate' => $cycle->delivery_date?->toDateString(),
            'slotNumber' => $cycle->deliverySlot?->slot_number,
            'status' => $cycle->status,
            'planQty' => (int) $cycle->items->sum('quantity'),
            'actualQty' => (int) $cycle->items->sum('received_quantity'),
            'itemCount' => $cycle->items->count(),
        ]);

        return response()->json($cycles);
    }

    /**
     * Resolve the monitoring date from the request, falling back to today
     * when no date is given or the provided value is unparseable.
     */
    private function resolveDate(Request $request): Carbon
    {
        if (! $request->filled('date')) {
            return now();
        }

        try {
            return Carbon::parse($request->input('date'));
        } catch (InvalidFormatException) {
            return now();
        }
    }
}
