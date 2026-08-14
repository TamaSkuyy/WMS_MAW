<?php

namespace App\Http\Controllers;

use App\Events\StockChanged;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShoppingController extends Controller
{
    public function index(Request $request)
    {
        $shoppings = Shopping::with(['shoppingLocation', 'items'])
            ->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->whereHas('shoppingLocation', fn ($ql) => $ql->where('name', 'like', "%{$s}%")))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Transactions/Shopping/Index', [
            'shoppings' => $shoppings,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        return Inertia::render('Transactions/Shopping/Create', [
            'products'           => Product::with(['vehicleModel', 'stocks', 'supplier'])->where('is_active', true)->orderBy('name')->get(),
            'racks'              => Rack::orderBy('zone')->orderBy('code')->get(),
            'shoppingLocations'  => ShoppingLocation::orderBy('name')->get(),
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
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            'shopping_location_id' => 'required|exists:shopping_locations,id',
            'shopping_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'frame_number' => 'nullable|string|max:100',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $shopping = Shopping::create([
            'shopping_location_id' => $validated['shopping_location_id'],
            'shopping_date' => $validated['shopping_date'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'frame_number' => $validated['frame_number'] ?? null,
        ]);

        if (!empty($validated['items'])) {
            $items = $this->mergeDuplicateItems($validated['items']);
            foreach ($items as $item) {
                $shopping->items()->create([
                    'product_id' => $item['product_id'],
                    'rack_id' => $item['rack_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        return redirect()->route('shoppings.show', $shopping)->with('success', 'Shopping berhasil dibuat.');
    }

    public function show(Shopping $shopping)
    {
        return Inertia::render('Transactions/Shopping/Show', [
            'shopping' => $shopping->load('items.product.vehicleModel', 'items.rack', 'shoppingLocation'),
        ]);
    }

    public function edit(Shopping $shopping)
    {
        abort_unless(auth()->user()->can('edit shoppings'), 403);

        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Only draft shopping records can be edited.');
        }

        return Inertia::render('Transactions/Shopping/Edit', [
            'shopping'           => $shopping->load('items.product.vehicleModel', 'shoppingLocation'),
            'products'           => Product::with(['vehicleModel', 'stocks', 'supplier'])->where('is_active', true)->orderBy('name')->get(),
            'racks'              => Rack::orderBy('zone')->orderBy('code')->get(),
            'shoppingLocations'  => ShoppingLocation::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Shopping $shopping)
    {
        abort_unless(auth()->user()->can('edit shoppings'), 403);

        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Only draft shopping records can be edited.');
        }

        $validated = $request->validate([
            'shopping_location_id' => 'required|exists:shopping_locations,id',
            'shopping_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'frame_number' => 'nullable|string|max:100',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $shopping->update([
            'shopping_location_id' => $validated['shopping_location_id'],
            'shopping_date' => $validated['shopping_date'],
            'notes' => $validated['notes'] ?? null,
            'frame_number' => $validated['frame_number'] ?? null,
        ]);

        $shopping->items()->delete();
        if (!empty($validated['items'])) {
            $items = $this->mergeDuplicateItems($validated['items']);
            foreach ($items as $item) {
                $shopping->items()->create([
                    'product_id' => $item['product_id'],
                    'rack_id' => $item['rack_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        return redirect()->route('shoppings.show', $shopping)->with('success', 'Shopping berhasil diupdate.');
    }

    public function destroy(Shopping $shopping)
    {
        abort_unless(auth()->user()->can('delete shoppings'), 403);

        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Only draft shopping records can be deleted.');
        }

        $shopping->delete();

        return redirect()->route('shoppings.index')->with('success', 'Shopping berhasil dihapus.');
    }

    public function ship(Request $request, Shopping $shopping)
    {
        abort_unless(auth()->user()->can('ship shoppings'), 403);

        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Tidak dapat memproses shopping ini.');
        }

        $result = DB::transaction(function () use ($shopping) {
            $lockedShopping = Shopping::where('id', $shopping->id)->lockForUpdate()->firstOrFail();

            if ($lockedShopping->status !== 'draft') {
                return ['ok' => false, 'error' => 'Tidak dapat memproses shopping ini.'];
            }

            $items = $lockedShopping->items()->with('product', 'rack')->get();

            if ($items->isEmpty()) {
                return ['ok' => false, 'error' => 'Tidak dapat memproses: part tidak lengkap — shopping ini tidak memiliki item.'];
            }

            $lockedStocks = [];

            foreach ($items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('rack_id', $item->rack_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $item->quantity) {
                    $productName = $item->product?->name ?? 'Unknown';
                    $rackCode = $item->rack?->code ?? '(relay)';

                    return [
                        'ok' => false,
                        'error' => "Stok tidak mencukupi: {$productName} di rak {$rackCode}. Tersedia: "
                            . ($stock->quantity ?? 0)
                            . ", Dibutuhkan: {$item->quantity}",
                    ];
                }

                $lockedStocks[$item->id] = $stock;
            }

            foreach ($items as $item) {
                $stock = $lockedStocks[$item->id];
                $stock->quantity -= $item->quantity;
                $stock->save();
            }

            $lockedShopping->update(['status' => 'shipped']);

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        try {
            event(new StockChanged());
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('shoppings.show', $shopping)->with('success', 'Shopping diproses. Stok dikurangi.');
    }
}
