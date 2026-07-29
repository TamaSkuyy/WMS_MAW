<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $stocks = Stock::with(['product.supplier', 'product.vehicleModel', 'rack'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('part_number', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->rack_id, function ($query, $rackId) {
                $query->where('rack_id', $rackId);
            })
            ->when($request->zone, function ($query, $zone) {
                $query->whereHas('rack', function ($q) use ($zone) {
                    $q->where('zone', $zone);
                });
            })
            ->orderBy('quantity', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Collect all (product_id, rack_id) pairs from current page
        $pairs = $stocks->map(fn($s) => [
            'product_id' => $s->product_id,
            'rack_id' => $s->rack_id,
        ])->unique(fn($p) => $p['product_id'] . '-' . ($p['rack_id'] ?? 'null'));

        // --- Total Masuk (accumulated stock in from completed cycles) ---
        $totalIn = \App\Models\CycleItem::selectRaw('product_id, rack_id, SUM(received_quantity) as total_in')
            ->whereHas('cycle', fn($q) => $q->where('status', 'completed'))
            ->whereIn('product_id', $pairs->pluck('product_id'))
            ->groupBy('product_id', 'rack_id')
            ->get()
            ->mapWithKeys(fn($row) => [
                $row->product_id . '-' . ($row->rack_id ?? 'null') => (int) $row->total_in,
            ]);

        // --- Total Keluar (accumulated stock out from completed shoppings) ---
        $totalOut = \App\Models\ShoppingItem::selectRaw('product_id, rack_id, SUM(quantity) as total_out')
            ->whereHas('shopping', fn($q) => $q->where('status', 'completed'))
            ->whereIn('product_id', $pairs->pluck('product_id'))
            ->groupBy('product_id', 'rack_id')
            ->get()
            ->mapWithKeys(fn($row) => [
                $row->product_id . '-' . ($row->rack_id ?? 'null') => (int) $row->total_out,
            ]);

        // Attach totals to each stock record
        $stocks->getCollection()->transform(function ($stock) use ($totalIn, $totalOut) {
            $key = $stock->product_id . '-' . ($stock->rack_id ?? 'null');
            $stock->total_in = $totalIn[$key] ?? 0;
            $stock->total_out = $totalOut[$key] ?? 0;
            return $stock;
        });

        return Inertia::render('Transactions/Stocks/Index', [
            'stocks' => $stocks,
            'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
            'zones' => Rack::select('zone')->distinct()->orderBy('zone')->pluck('zone'),
            'filters' => $request->only(['search', 'rack_id', 'zone']),
        ]);
    }
}
