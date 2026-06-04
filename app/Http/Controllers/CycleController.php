<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CycleController extends Controller
{
    public function index(Request $request)
    {
        $cycles = Cycle::with('supplier')
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Transactions/Cycles/Index', [
            'cycles' => $cycles,
            'suppliers' => Supplier::orderBy('name')->get(),
            'filters' => $request->only(['supplier_id', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Transactions/Cycles/Create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'cycle_number' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $cycle = Cycle::create([
            'supplier_id' => $validated['supplier_id'],
            'cycle_number' => $validated['cycle_number'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $cycle->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('cycles.show', $cycle)->with('success', 'Cycle created.');
    }

    public function show(Cycle $cycle)
    {
        return Inertia::render('Transactions/Cycles/Show', [
            'cycle' => $cycle->load(['supplier', 'items.product.vehicleModel', 'items.product.category']),
            'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
        ]);
    }

    public function edit(Cycle $cycle)
    {
        if ($cycle->status !== 'draft') {
            return back()->with('error', 'Only draft cycles can be edited.');
        }
        return Inertia::render('Transactions/Cycles/Edit', [
            'cycle' => $cycle->load('items.product'),
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Cycle $cycle)
    {
        if ($cycle->status !== 'draft') {
            return back()->with('error', 'Only draft cycles can be edited.');
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'cycle_number' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $cycle->update([
            'supplier_id' => $validated['supplier_id'],
            'cycle_number' => $validated['cycle_number'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Replace items
        $cycle->items()->delete();
        foreach ($validated['items'] as $item) {
            $cycle->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('cycles.show', $cycle)->with('success', 'Cycle updated.');
    }

    public function destroy(Cycle $cycle)
    {
        if ($cycle->status !== 'draft') {
            return back()->with('error', 'Only draft cycles can be deleted.');
        }
        $cycle->delete();
        return redirect()->route('cycles.index')->with('success', 'Cycle deleted.');
    }

    /**
     * Receive items and complete the cycle — add to stock.
     */
    public function receive(Request $request, Cycle $cycle)
    {
        if ($cycle->status !== 'draft' && $cycle->status !== 'receiving') {
            return back()->with('error', 'Cannot receive this cycle.');
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:cycle_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.notes' => 'nullable|string|max:200',
        ]);

        // Update received quantities
        foreach ($validated['items'] as $itemData) {
            $item = CycleItem::where('id', $itemData['id'])
                ->where('cycle_id', $cycle->id)
                ->firstOrFail();
            $item->update([
                'received_quantity' => $itemData['received_quantity'],
                'notes' => $itemData['notes'] ?? null,
            ]);

            // Add to stock
            if ($itemData['received_quantity'] > 0) {
                $stock = Stock::firstOrNew([
                    'product_id' => $item->product_id,
                    'rack_id' => $itemData['rack_id'],
                ]);
                $stock->quantity += $itemData['received_quantity'];
                $stock->save();
            }
        }

        $cycle->update([
            'status' => 'completed',
            'received_at' => now(),
        ]);

        return redirect()->route('cycles.show', $cycle)->with('success', 'Cycle completed. Stock updated.');
    }
}
