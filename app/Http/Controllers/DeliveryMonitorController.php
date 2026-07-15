<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Supplier;
use App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryMonitorController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : now();

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
}
