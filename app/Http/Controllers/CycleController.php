<?php

namespace App\Http\Controllers;

use App\Events\StockChanged;
use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\DeliverySlot;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\CycleExporter;
use App\Services\ImportExport\Imports\CycleImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CycleController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new CycleImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new CycleExporter();
    }

    protected function exportFileName(): string
    {
        return 'cycles-export';
    }
    public function index(Request $request)
    {
        $cycles = Cycle::with('supplier')
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(10)
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

    private function mergeDuplicateItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $item['product_id'];
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
            'supplier_id' => 'required|exists:suppliers,id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        $slot = DeliverySlot::currentForTime(now());
        // Auto-assigned, never client-supplied — avoids duplicate-key errors
        // from users guessing/reusing a cycle_number for the same supplier.
        $cycleNumber = (Cycle::where('supplier_id', $validated['supplier_id'])->max('cycle_number') ?? 0) + 1;

        $cycle = Cycle::create([
            'supplier_id' => $validated['supplier_id'],
            'cycle_number' => $cycleNumber,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'delivery_date' => now()->toDateString(),
            'delivery_slot_id' => $slot?->id,
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
        $cycle->load(['supplier', 'items.product.vehicleModel', 'items.product.category', 'items.product.defaultRack', 'items.receiveLogs.user']);

        $productIds = $cycle->items->pluck('product_id')->toArray();
        $lastUsedRacks = CycleItem::whereIn('product_id', $productIds)
            ->whereNotNull('rack_id')
            ->where('cycle_id', '!=', $cycle->id)
            ->orderByDesc('updated_at')
            ->get()
            ->unique('product_id')
            ->pluck('rack_id', 'product_id');

        return Inertia::render('Transactions/Cycles/Show', [
            'cycle' => $cycle,
            'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
            'lastUsedRacks' => $lastUsedRacks,
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
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        // cycle_number is server-assigned at creation and never editable —
        // changing it here would risk colliding with another cycle's number
        // for the same supplier.
        $cycle->update([
            'supplier_id' => $validated['supplier_id'],
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
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.notes' => 'nullable|string|max:200',
        ]);

        $userId = $request->user()?->id;
        $now = now();

        $ok = DB::transaction(function () use ($validated, $cycle, $userId, $now) {
            $lockedCycle = Cycle::where('id', $cycle->id)->lockForUpdate()->firstOrFail();

            if ($lockedCycle->status !== 'draft' && $lockedCycle->status !== 'receiving') {
                return false;
            }

            foreach ($validated['items'] as $itemData) {
                $item = CycleItem::where('id', $itemData['id'])
                    ->where('cycle_id', $lockedCycle->id)
                    ->firstOrFail();

                $oldReceived = (int) $item->received_quantity;
                $newReceived = (int) $itemData['received_quantity'];
                $delta = $newReceived - $oldReceived;

                if ($delta !== 0) {
                    // Log this receive action
                    \App\Models\ReceiveLog::create([
                        'cycle_item_id' => $item->id,
                        'quantity'      => $delta,
                        'rack_id'       => $itemData['rack_id'] ?? null,
                        'user_id'       => $userId,
                        'notes'         => $itemData['notes'] ?? null,
                        'created_at'    => $now,
                    ]);
                }

                $item->update([
                    'received_quantity' => $newReceived,
                    'rack_id'           => $itemData['rack_id'] ?? null,
                    'notes'             => $itemData['notes'] ?? null,
                ]);

                if ($delta !== 0) {
                    $stock = Stock::where('product_id', $item->product_id)
                        ->where('rack_id', $itemData['rack_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $stock) {
                        $stock = Stock::create([
                            'product_id' => $item->product_id,
                            'rack_id' => $itemData['rack_id'] ?? null,
                            'quantity' => 0,
                        ]);
                    }

                    $stock->quantity += $delta;
                    $stock->save();
                }
            }

            // Check if ALL items are fully received
            $allComplete = $lockedCycle->items()->whereRaw('received_quantity < quantity')->count() === 0;

            $lockedCycle->update([
                'status'      => $allComplete ? 'completed' : 'receiving',
                'received_at' => $allComplete ? $now : null,
            ]);

            return true;
        });

        if (! $ok) {
            return back()->with('error', 'Cannot receive this cycle.');
        }

        try {
            event(new StockChanged(supplierId: $cycle->supplier_id));
        } catch (\Throwable $e) {
            report($e);
        }

        $msg = $cycle->fresh()->status === 'completed'
            ? 'Cycle completed. All items received.'
            : 'Penerimaan sebagian berhasil. Cycle masih receiving.';

        return redirect()->route('cycles.show', $cycle)->with('success', $msg);
    }

    public function quickReceiveForm()
    {
        return Inertia::render('Transactions/Cycles/QuickReceive', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products'  => Product::with('defaultRack')->where('is_active', true)->orderBy('name')->get(),
            'racks'     => Rack::orderBy('zone')->orderBy('code')->get(),
        ]);
    }

    public function quickReceiveStore(Request $request)
    {
        $validated = $request->validate([
            'supplier_id'        => 'required|exists:suppliers,id',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id'    => 'nullable|exists:racks,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $cycle = DB::transaction(function () use ($validated) {
            $supplierId  = $validated['supplier_id'];
            $cycleNumber = (Cycle::where('supplier_id', $supplierId)->max('cycle_number') ?? 0) + 1;
            $slot = DeliverySlot::currentForTime(now());

            $cycle = Cycle::create([
                'supplier_id'  => $supplierId,
                'cycle_number' => $cycleNumber,
                'status'       => 'completed',
                'received_at'  => now(),
                'delivery_date' => now()->toDateString(),
                'delivery_slot_id' => $slot?->id,
            ]);

            foreach ($validated['items'] as $item) {
                $cycle->items()->create([
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'received_quantity' => $item['quantity'],
                    'rack_id'           => $item['rack_id'] ?? null,
                ]);

                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('rack_id', $item['rack_id'] ?? null)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    $stock = Stock::create([
                        'product_id' => $item['product_id'],
                        'rack_id'    => $item['rack_id'] ?? null,
                        'quantity'   => 0,
                    ]);
                }

                $stock->quantity += $item['quantity'];
                $stock->save();
            }

            return $cycle;
        });

        try {
            event(new StockChanged(supplierId: $cycle->supplier_id));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('cycles.show', $cycle)->with('success', 'Barang diterima. Stock diperbarui.');
    }
}
