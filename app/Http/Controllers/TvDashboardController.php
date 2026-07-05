<?php

namespace App\Http\Controllers;

use App\Models\CycleItem;
use App\Models\Product;
use App\Models\ShipmentItem;
use App\Models\Stock;
use Inertia\Inertia;

class TvDashboardController extends Controller
{
    public function index()
    {
        $products = Product::with('vehicleModel')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = $products->map(function (Product $product) {
            $totalStock = (int) Stock::where('product_id', $product->id)->sum('quantity');

            $lastReceivedItem = CycleItem::where('product_id', $product->id)
                ->where('received_quantity', '>', 0)
                ->whereHas('cycle', fn ($q) => $q->where('status', 'completed'))
                ->with('cycle')
                ->orderByDesc('updated_at')
                ->first();

            $lastShippedItem = ShipmentItem::where('product_id', $product->id)
                ->whereHas('shipment', fn ($q) => $q->where('status', 'shipped'))
                ->with('shipment')
                ->orderByDesc('updated_at')
                ->first();

            return [
                'id' => $product->id,
                'part_number' => $product->part_number,
                'name' => $product->name,
                'vehicle_model' => $product->vehicleModel ? [
                    'brand' => $product->vehicleModel->brand,
                    'name' => $product->vehicleModel->name,
                    'suffix' => $product->vehicleModel->suffix,
                ] : null,
                'total_stock' => $totalStock,
                'last_received' => $lastReceivedItem ? [
                    'quantity' => $lastReceivedItem->received_quantity,
                    'date' => optional($lastReceivedItem->cycle->received_at)->toIso8601String(),
                ] : null,
                'last_shipped' => $lastShippedItem ? [
                    'quantity' => $lastShippedItem->quantity,
                    'date' => $lastShippedItem->shipment->updated_at->toIso8601String(),
                ] : null,
            ];
        });

        $slides = $items->chunk(6)->values()->map(fn ($chunk) => $chunk->values())->all();

        return Inertia::render('TvDashboard/Index', [
            'slides' => $slides,
        ]);
    }
}
