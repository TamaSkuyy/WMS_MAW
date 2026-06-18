<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Stock;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::where('is_active', true)->count();

        $lowStockCount = Stock::where('quantity', '<', 5)
            ->where('quantity', '>', 0)
            ->count();

        $lowStockItems = Stock::with(['product', 'rack'])
            ->where('quantity', '<', 5)
            ->where('quantity', '>', 0)
            ->orderBy('quantity')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'part_number' => $s->product?->part_number,
                'name' => $s->product?->name,
                'rack' => $s->rack?->code,
                'quantity' => $s->quantity,
            ]);

        $pendingCycles = Cycle::with('supplier')
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'supplier' => $c->supplier?->name,
                'cycle_number' => $c->cycle_number,
                'items_count' => $c->items()->count(),
                'created_at' => $c->created_at->format('d M Y'),
            ]);

        $todayShipments = Shipment::withCount('items')
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'partner_name' => $s->partner_name,
                'shipment_date' => $s->shipment_date->format('d M Y'),
                'items_count' => $s->items_count,
                'status' => $s->status,
            ]);

        $totalStock = Stock::sum('quantity');

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_products' => $totalProducts,
                'total_stock' => $totalStock,
                'low_stock_count' => $lowStockCount,
                'pending_cycles' => Cycle::where('status', 'draft')->count(),
                'pending_shipments' => Shipment::where('status', 'draft')->count(),
                'completed_cycles_today' => Cycle::where('status', 'completed')
                    ->whereDate('received_at', today())
                    ->count(),
            ],
            'lowStockItems' => $lowStockItems,
            'pendingCycles' => $pendingCycles,
            'todayShipments' => $todayShipments,
        ]);
    }
}
