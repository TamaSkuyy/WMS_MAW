<?php

namespace App\Http\Controllers;

use App\Events\StockChanged;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shipment;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $shipments = Shipment::withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('partner_name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Transactions/Shipments/Index', [
            'shipments' => $shipments,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Transactions/Shipments/Create', [
            'products'      => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
            'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
            'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
        ]);
    }

    private function mergeDuplicateItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $item['product_id'] . '-' . $item['rack_id'];
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $item['quantity'];
            } else {
                $merged[$key] = $item;
            }
        }
        return array_values($merged);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_name' => 'required|string|max:255',
            'shipment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        $shipment = Shipment::create([
            'partner_name' => $validated['partner_name'],
            'shipment_date' => $validated['shipment_date'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $shipment->items()->create([
                'product_id' => $item['product_id'],
                'rack_id' => $item['rack_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment created.');
    }

    public function show(Shipment $shipment)
    {
        return Inertia::render('Transactions/Shipments/Show', [
            'shipment' => $shipment->load('items.product.vehicleModel', 'items.rack'),
        ]);
    }

    public function edit(Shipment $shipment)
    {
        if ($shipment->status !== 'draft') {
            return back()->with('error', 'Only draft shipments can be edited.');
        }

        return Inertia::render('Transactions/Shipments/Edit', [
            'shipment'      => $shipment->load('items.product.vehicleModel'),
            'products'      => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
            'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
            'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
        ]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        if ($shipment->status !== 'draft') {
            return back()->with('error', 'Only draft shipments can be edited.');
        }

        $validated = $request->validate([
            'partner_name' => 'required|string|max:255',
            'shipment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        $shipment->update([
            'partner_name' => $validated['partner_name'],
            'shipment_date' => $validated['shipment_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $shipment->items()->delete();
        foreach ($validated['items'] as $item) {
            $shipment->items()->create([
                'product_id' => $item['product_id'],
                'rack_id' => $item['rack_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment updated.');
    }

    public function destroy(Shipment $shipment)
    {
        if ($shipment->status !== 'draft') {
            return back()->with('error', 'Only draft shipments can be deleted.');
        }

        $shipment->delete();

        return redirect()->route('shipments.index')->with('success', 'Shipment deleted.');
    }

    /**
     * Ship the items — deduct from stock.
     */
    public function ship(Request $request, Shipment $shipment)
    {
        if ($shipment->status !== 'draft') {
            return back()->with('error', 'Cannot ship this shipment.');
        }

        $result = DB::transaction(function () use ($shipment) {
            $lockedShipment = Shipment::where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if ($lockedShipment->status !== 'draft') {
                return ['ok' => false, 'error' => 'Cannot ship this shipment.'];
            }

            $items = $lockedShipment->items()->with('product', 'rack')->get();
            $lockedStocks = [];

            foreach ($items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('rack_id', $item->rack_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $item->quantity) {
                    $productName = $item->product->name ?? 'Unknown';
                    $rackCode = $item->rack->code ?? '?';

                    return [
                        'ok' => false,
                        'error' => "Insufficient stock: {$productName} in rack {$rackCode}. Available: "
                            . ($stock->quantity ?? 0)
                            . ", Requested: {$item->quantity}",
                    ];
                }

                $lockedStocks[$item->id] = $stock;
            }

            foreach ($items as $item) {
                $stock = $lockedStocks[$item->id];
                $stock->quantity -= $item->quantity;
                $stock->save();
            }

            $lockedShipment->update(['status' => 'shipped']);

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        event(new StockChanged());

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment processed. Stock deducted.');
    }
}
