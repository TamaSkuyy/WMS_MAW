<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\Stock;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::where('is_active', true)->count();

        // Low stock: stock quantity < product.min_stock (min_stock must be set)
        $lowStockQuery = Stock::with(['product', 'rack'])
            ->whereHas('product', fn($q) => $q->whereNotNull('min_stock'))
            ->whereRaw('stocks.quantity < (SELECT min_stock FROM products WHERE products.id = stocks.product_id)')
            ->where('quantity', '>', 0);

        $lowStockCount = $lowStockQuery->count();

        $lowStockItems = (clone $lowStockQuery)
            ->orderBy('quantity')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'part_number' => $s->product?->part_number,
                'name' => $s->product?->name,
                'rack' => $s->rack?->code,
                'quantity' => $s->quantity,
                'min_stock' => $s->product?->min_stock,
            ]);

        // Overstock: stock quantity > product.max_stock (max_stock must be set)
        $overStockQuery = Stock::with(['product', 'rack'])
            ->whereHas('product', fn($q) => $q->whereNotNull('max_stock'))
            ->whereRaw('stocks.quantity > (SELECT max_stock FROM products WHERE products.id = stocks.product_id)');

        $overStockCount = $overStockQuery->count();

        $overStockItems = (clone $overStockQuery)
            ->orderBy('quantity', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'part_number' => $s->product?->part_number,
                'name' => $s->product?->name,
                'rack' => $s->rack?->code,
                'quantity' => $s->quantity,
                'max_stock' => $s->product?->max_stock,
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

        $todayShoppings = Shopping::with('shoppingLocation')
            ->withCount('items')
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'shopping_location' => $s->shoppingLocation?->name,
                'shopping_date' => $s->shopping_date->format('d M Y'),
                'items_count' => $s->items_count,
                'status' => $s->status,
            ]);

        $totalStock = Stock::sum('quantity');

        // Recent completed cycles with duration
        $recentCycles = Cycle::with('supplier')
            ->where('status', 'completed')
            ->whereNotNull('received_at')
            ->orderBy('received_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'supplier' => $c->supplier?->name,
                'cycle_number' => $c->cycle_number,
                'items_count' => $c->items()->count(),
                'created_at' => $c->created_at->format('d M H:i'),
                'received_at' => $c->received_at->format('d M H:i'),
                'duration_minutes' => (int) $c->created_at->diffInMinutes($c->received_at),
            ]);

        // Avg duration for cycles completed today
        $avgDurationToday = Cycle::where('status', 'completed')
            ->whereNotNull('received_at')
            ->whereDate('received_at', today())
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, received_at)) as avg_seconds')
            ->value('avg_seconds');

        // All racks with capacity usage
        $rackAlerts = Rack::withSum('stocks', 'quantity')
            ->get()
            ->map(function ($rack) {
                $usage = (int) ($rack->stocks_sum_quantity ?? 0);
                $cap = $rack->capacity ? (int) $rack->capacity : null;
                $pct = $cap ? (int) round(($usage / $cap) * 100) : null;
                return [
                    'id' => $rack->id,
                    'code' => $rack->code,
                    'zone' => $rack->zone,
                    'usage' => $usage,
                    'capacity' => $cap,
                    'pct' => $pct,
                ];
            })
            ->sortByDesc(function ($r) {
                return $r['pct'] ?? -1;
            })
            ->values();

        $rackFullCount = $rackAlerts->where('pct', '>=', 100)->count();
        $rackNearFullCount = $rackAlerts->where('pct', '>=', 80)->where('pct', '<', 100)->count();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_products' => $totalProducts,
                'total_stock' => $totalStock,
                'low_stock_count' => $lowStockCount,
                'over_stock_count' => $overStockCount,
                'pending_cycles' => Cycle::where('status', 'draft')->count(),
                'pending_shoppings' => Shopping::where('status', 'draft')->count(),
                'completed_cycles_today' => Cycle::where('status', 'completed')
                    ->whereDate('received_at', today())
                    ->count(),
            ],
            'lowStockItems' => $lowStockItems,
            'overStockItems' => $overStockItems,
            'pendingCycles' => $pendingCycles,
            'todayShoppings' => $todayShoppings,
            'recentCycles' => $recentCycles,
            'avgDurationToday' => $avgDurationToday ? (int) $avgDurationToday : null,
            'rackAlerts' => $rackAlerts,
            'rackFullCount' => $rackFullCount,
            'rackNearFullCount' => $rackNearFullCount,
            'totalRacks' => Rack::count(),
            'racksWithCapacity' => Rack::whereNotNull('capacity')->count(),
        ]);
    }
}
