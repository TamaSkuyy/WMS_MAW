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

        return Inertia::render('Transactions/Stocks/Index', [
            'stocks' => $stocks,
            'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
            'zones' => Rack::select('zone')->distinct()->orderBy('zone')->pluck('zone'),
            'filters' => $request->only(['search', 'rack_id', 'zone']),
        ]);
    }
}
