<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\ReceiveLog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['supplier_id', 'period', 'date_from', 'date_to']);

        $query = Cycle::with(['supplier', 'items.product', 'items.receiveLogs'])
            ->where('status', 'completed');

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('received_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('received_at', '<=', $filters['date_to']);
        }

        $cycles = $query->latest('received_at')->paginate(15)->withQueryString();

        // --- Metrics ---
        $metricsQuery = Cycle::where('status', 'completed');
        if (!empty($filters['supplier_id'])) $metricsQuery->where('supplier_id', $filters['supplier_id']);
        if (!empty($filters['date_from'])) $metricsQuery->whereDate('received_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $metricsQuery->whereDate('received_at', '<=', $filters['date_to']);

        $totalCycles = $metricsQuery->count();
        $onTimeCount = (clone $metricsQuery)->whereNotNull('delivery_date')
            ->whereRaw('DATE(received_at) <= delivery_date')->count();
        $onTimeRate = $totalCycles > 0 ? round(($onTimeCount / $totalCycles) * 100) : 0;

        // --- Incomplete items ---
        $incompleteItems = \App\Models\CycleItem::whereHas('cycle', function ($q) use ($filters) {
            $q->where('status', 'completed');
            if (!empty($filters['supplier_id'])) $q->where('supplier_id', $filters['supplier_id']);
            if (!empty($filters['date_from'])) $q->whereDate('received_at', '>=', $filters['date_from']);
            if (!empty($filters['date_to'])) $q->whereDate('received_at', '<=', $filters['date_to']);
        })->whereRaw('received_quantity < quantity')
            ->with(['product', 'cycle.supplier'])
            ->limit(20)
            ->get()
            ->map(fn($item) => [
                'cycle_id' => $item->cycle_id,
                'cycle_number' => $item->cycle->cycle_number,
                'supplier' => $item->cycle->supplier->name,
                'part_number' => $item->product->part_number,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'received_quantity' => $item->received_quantity,
                'shortfall' => $item->quantity - $item->received_quantity,
                'received_at' => $item->cycle->received_at?->format('d M Y'),
            ]);

        // --- Per-supplier summary ---
        $perSupplier = \App\Models\Cycle::selectRaw('supplier_id, COUNT(*) as total_cycles, COUNT(CASE WHEN DATE(received_at) <= delivery_date THEN 1 END) as on_time')
            ->where('status', 'completed')
            ->when(!empty($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('received_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($q) => $q->whereDate('received_at', '<=', $filters['date_to']))
            ->groupBy('supplier_id')
            ->with('supplier')
            ->get()
            ->map(fn($c) => [
                'supplier' => $c->supplier->name,
                'total' => $c->total_cycles,
                'on_time' => $c->on_time,
                'rate' => $c->total_cycles > 0 ? round(($c->on_time / $c->total_cycles) * 100) : 0,
            ]);

        return Inertia::render('Reports/SupplierPerformance', [
            'cycles' => $cycles,
            'incompleteItems' => $incompleteItems,
            'perSupplier' => $perSupplier,
            'metrics' => [
                'total_cycles' => $totalCycles,
                'on_time_rate' => $onTimeRate,
                'incomplete_count' => $incompleteItems->count(),
            ],
            'suppliers' => Supplier::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }
}
