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
use App\Models\User;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\CycleExporter;
use App\Services\ImportExport\Imports\CycleImporter;
use App\Services\DataOrder\DataOrderImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $cycles = Cycle::with(['supplier', 'creator', 'carrier'])
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
        abort_unless(auth()->user()->can('create cycles'), 403);

        return Inertia::render('Transactions/Cycles/Create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name']),
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
        abort_unless(auth()->user()->can('create cycles'), 403);

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'carrier_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        $slot = DeliverySlot::currentForTime(now());
        // Nomor cycle = nomor gelombang HARI ITU per supplier (mulai 1 tiap
        // tanggal), bukan nomor global yang terus bertambah.
        $deliveryDate = now()->toDateString();
        $cycleNumber = $this->nextCycleNumberFor($validated['supplier_id'], $deliveryDate);

        $cycle = Cycle::create([
            'supplier_id' => $validated['supplier_id'],
            'carrier_id' => $validated['carrier_id'] ?? null,
            'cycle_number' => $cycleNumber,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'delivery_date' => $deliveryDate,
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

    /**
     * Preview file "Data Order" supplier (lihat DataOrderImportService) →
     * ringkasan cycle draft yang akan dibuat. Belum menyentuh database.
     */
    public function dataOrderPreview(Request $request)
    {
        abort_unless(auth()->user()->can('create cycles'), 403);

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'delivery_date' => 'required|date',
        ]);

        return response()->json(
            app(DataOrderImportService::class)->preview($request->file('file'), $validated['delivery_date'])
        );
    }

    /**
     * Terapkan hasil preview file Data Order: buat satu cycle draft per
     * (supplier × gelombang/CYCLE yang berisi qty). Baris di-resolve ulang
     * dari master di sisi server.
     *
     * File supplier sering di-update (data lama tetap ada). Karena itu mode
     * "replace" menghapus cycle DRAFT hasil import Data Order tanggal tsb
     * lalu membuat ulang versi terbaru; "append" hanya menambah.
     */
    public function dataOrderApply(Request $request)
    {
        abort_unless(auth()->user()->can('create cycles'), 403);

        $validated = $request->validate([
            'delivery_date' => 'required|date',
            'mode' => 'required|in:append,replace',
            'rows' => 'required|array|min:1|max:20000',
            'rows.*.supplier_code' => 'required|string|max:20',
            'rows.*.cycle' => 'required|integer|min:1|max:99',
            'rows.*.part_number' => 'required|string|max:100',
            'rows.*.quantity' => 'required|integer|min:1|max:1000000',
        ]);

        return response()->json(
            app(DataOrderImportService::class)->apply(
                $validated['rows'],
                $validated['delivery_date'],
                $validated['mode'],
            )
        );
    }

    /**
     * Nomor cycle berikutnya utk (supplier × delivery_date):
     * max(cycle_number) hari itu + 1 — reset ke 1 setiap tanggal baru.
     */
    private function nextCycleNumberFor(int $supplierId, string $deliveryDate): int
    {
        return (int) Cycle::where('supplier_id', $supplierId)
            ->whereDate('delivery_date', $deliveryDate)
            ->max('cycle_number') + 1;
    }

    /**
     * Data untuk modal pemilih "Terima Barang": daftar cycle yang siap
     * diterima — status draft (hasil import/manual, belum diterima) dan
     * receiving yang masih punya sisa terima (received_quantity < quantity).
     *
     * Filter: supplier_id (mitra) + rentang delivery_date (tanggal dokumen).
     * Kolom "actual/plan" = total received_quantity / total quantity per cycle.
     */
    public function receivePicker(Request $request)
    {
        abort_unless(auth()->user()->can('receive cycles'), 403);

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date',
        ]);

        $cycles = Cycle::query()
            ->with('supplier:id,name')
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->withSum('items', 'received_quantity')
            ->where(function ($q) {
                $q->where('status', 'draft')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'receiving')
                         ->whereHas('items', fn ($qi) => $qi->whereColumn('received_quantity', '<', 'quantity'));
                  });
            })
            ->when($validated['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->whereDate('delivery_date', '>=', $d))
            ->when($validated['date_to'] ?? null, fn ($q, $d) => $q->whereDate('delivery_date', '<=', $d))
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'cycles' => $cycles->map(fn (Cycle $c) => [
                'id'            => $c->id,
                'cycle_number'  => $c->cycle_number,
                'supplier'      => $c->supplier?->name ?? '-',
                'delivery_date' => optional($c->delivery_date)->format('Y-m-d'),
                'status'        => $c->status,
                'items_count'   => $c->items_count,
                'plan_qty'      => (int) $c->items_sum_quantity,
                'received_qty'  => (int) $c->items_sum_received_quantity,
            ]),
        ]);
    }

    public function show(Request $request, Cycle $cycle)
    {
        $cycle->load(['supplier', 'creator', 'carrier', 'items.product.vehicleModel', 'items.product.category', 'items.product.defaultRack', 'items.receiveLogs.user']);

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
            // Dari modal pemilih "Terima Barang" (?receive=1) → langsung buka form receive
            'autoReceive' => (bool) $request->query('receive'),
            // Riwayat koreksi + izin (tombol Koreksi hanya utk user yg boleh correct)
            'corrections' => $cycle->corrections()->with('user:id,name')->orderByDesc('id')->get(),
            'canCorrect' => auth()->user()->can('correct cycles'),
        ]);
    }

    public function edit(Cycle $cycle)
    {
        abort_unless(auth()->user()->can('edit cycles'), 403);

        if ($cycle->status !== 'draft') {
            return back()->with('error', 'Only draft cycles can be edited.');
        }
        return Inertia::render('Transactions/Cycles/Edit', [
            'cycle' => $cycle->load('items.product'),
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::with(['vehicleModel', 'category'])->where('is_active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Cycle $cycle)
    {
        abort_unless(auth()->user()->can('edit cycles'), 403);
        if ($cycle->status !== 'draft') {
            return back()->with('error', 'Only draft cycles can be edited.');
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'carrier_id' => 'nullable|exists:users,id',
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
            'carrier_id' => $validated['carrier_id'] ?? null,
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
        abort_unless(auth()->user()->can('delete cycles'), 403);
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
        abort_unless(auth()->user()->can('receive cycles'), 403);
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
        abort_unless(auth()->user()->can('create cycles'), 403);
        return Inertia::render('Transactions/Cycles/QuickReceive', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products'  => Product::with('defaultRack')->where('is_active', true)->orderBy('name')->get(),
            'racks'     => Rack::orderBy('zone')->orderBy('code')->get(),
            'users'     => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function quickReceiveStore(Request $request)
    {
        abort_unless(auth()->user()->can('create cycles'), 403);
        $validated = $request->validate([
            'supplier_id'        => 'required|exists:suppliers,id',
            'carrier_id'         => 'nullable|exists:users,id',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id'    => 'nullable|exists:racks,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $cycle = DB::transaction(function () use ($validated) {
            $supplierId  = $validated['supplier_id'];
            $deliveryDate = now()->toDateString();
            $cycleNumber = $this->nextCycleNumberFor($supplierId, $deliveryDate);
            $slot = DeliverySlot::currentForTime(now());

            $cycle = Cycle::create([
                'supplier_id'  => $supplierId,
                'carrier_id'   => $validated['carrier_id'] ?? null,
                'cycle_number' => $cycleNumber,
                'status'       => 'completed',
                'received_at'  => now(),
                'delivery_date' => $deliveryDate,
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

    // ── KOREKSI cycle yang SUDAH diterima (khusus permission correct cycles) ──
    // Prinsip anti-abuse: alasan wajib, delta stok otomatis (received/rak),
    // atomic + lock, ditolak bila stok tidak cukup, tercatat di riwayat.

    private function validateCycleCorrection(Request $request): array
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:cycle_items,id',
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.received_quantity' => 'required|integer|min:0|max:1000000',
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.notes' => 'nullable|string|max:200',
        ]);

        return $validated;
    }

    /** Snapshot item cycle (doc/received/rak) untuk log & delta. */
    private function cycleItemSnapshot($items): array
    {
        return $items->map(fn ($i) => [
            'id' => $i->id,
            'product_id' => $i->product_id,
            'part_number' => $i->product?->part_number ?? '#'.$i->product_id,
            'product_name' => $i->product?->name ?? '',
            'rack_id' => $i->rack_id,
            'rack_code' => $i->rack?->code ?? 'RELAY',
            'quantity' => (int) $i->quantity,
            'received_quantity' => (int) $i->received_quantity,
        ])->values()->all();
    }

    /**
     * Rencana delta stok dari perubahan received/rak per item (produk tetap).
     * Key: product_id|rack_id → delta (positif = stok bertambah).
     */
    private function cycleDeltaPlan(array $oldSnap, array $newItems, $cycleItems): array
    {
        $byId = collect($cycleItems)->keyBy('id');
        $delta = [];

        foreach ($newItems as $row) {
            $item = $byId->get($row['id']);
            if (! $item) {
                continue;
            }
            $productId = $item->product_id;
            $oldRecv = (int) $item->received_quantity;
            $newRecv = (int) $row['received_quantity'];
            $oldRack = $item->rack_id;
            $newRack = $row['rack_id'] ?? null;

            // Kembalikan ke rak lama (-) lalu tambah ke rak baru (+)
            $oldKey = $productId.'|'.($oldRack ?? 'null');
            $delta[$oldKey] ??= ['product_id' => $productId, 'rack_id' => $oldRack, 'delta' => 0];
            $delta[$oldKey]['delta'] -= $oldRecv;

            $newKey = $productId.'|'.($newRack ?? 'null');
            $delta[$newKey] ??= ['product_id' => $productId, 'rack_id' => $newRack, 'delta' => 0];
            $delta[$newKey]['delta'] += $newRecv;
        }

        return array_values($delta);
    }

    /** Preview koreksi cycle — hitung dampak stok tanpa mengubah apa pun. */
    public function correctPreview(Request $request, Cycle $cycle)
    {
        abort_unless(auth()->user()->can('correct cycles'), 403);

        if ($cycle->status === 'draft') {
            throw ValidationException::withMessages(['reason' => 'Cycle draft gunakan Edit biasa, bukan koreksi.']);
        }

        $validated = $this->validateCycleCorrection($request);

        $items = $cycle->items()->with(['product:id,part_number,name', 'rack:id,code'])->get();
        $oldSnap = $this->cycleItemSnapshot($items);
        $plan = $this->cycleDeltaPlan($oldSnap, $validated['items'], $items);

        [$lines, $errors] = $this->cycleDeltaLines($plan);

        return response()->json([
            'lines' => $lines,
            'errors' => $errors,
            'ok' => empty($errors),
        ]);
    }

    private function cycleDeltaLines(array $plan): array
    {
        $lines = [];
        $errors = [];

        $products = Product::whereIn('id', collect($plan)->pluck('product_id'))
            ->get(['id', 'part_number', 'name'])->keyBy('id');
        $racks = Rack::whereIn('id', collect($plan)->pluck('rack_id')->filter())
            ->get(['id', 'code'])->keyBy('id');

        foreach ($plan as $row) {
            $product = $products->get($row['product_id']);
            $rack = $row['rack_id'] ? $racks->get($row['rack_id']) : null;
            $rackCode = $rack?->code ?? ($row['rack_id'] === null ? 'RELAY' : '?');
            $stock = Stock::where('product_id', $row['product_id'])
                ->where('rack_id', $row['rack_id'])
                ->first();
            $current = $stock?->quantity ?? 0;
            $result = $current + $row['delta'];

            $lines[] = [
                'part_number' => $product->part_number ?? '',
                'product_name' => $product->name ?? '',
                'rack_code' => $rackCode,
                'current' => (int) $current,
                'delta' => (int) $row['delta'],
                'result' => $result,
            ];

            if ($result < 0) {
                $errors[] = "Stok tidak cukup untuk dikembalikan: {$product?->name} ({$product?->part_number}) di rak {$rackCode} — tersedia {$current}, perlu dikurangi ".abs($row['delta']);
            }
        }

        return [$lines, $errors];
    }

    /** Terapkan koreksi cycle final secara atomik. */
    public function correct(Request $request, Cycle $cycle)
    {
        abort_unless(auth()->user()->can('correct cycles'), 403);

        if ($cycle->status === 'draft') {
            return back()->with('error', 'Cycle draft gunakan Edit biasa.');
        }

        $validated = $this->validateCycleCorrection($request);

        // Qty dokumen tidak boleh kurang dari qty yang sudah diterima
        $itemIds = collect($validated['items'])->pluck('id');
        $cycleItems = $cycle->items()->whereIn('id', $itemIds)->get()->keyBy('id');
        foreach ($validated['items'] as $row) {
            $item = $cycleItems->get($row['id']);
            if (! $item) {
                return back()->with('error', 'Ada item yang bukan milik cycle ini.');
            }
            if ((int) $row['quantity'] < (int) $row['received_quantity']) {
                return back()->with('error', "Qty dokumen tidak boleh kurang dari qty diterima untuk item #{$item->product?->part_number}.");
            }
        }

        try {
            DB::transaction(function () use ($validated, $cycle, $itemIds) {
                $locked = Cycle::where('id', $cycle->id)->lockForUpdate()->firstOrFail();

                $items = $locked->items()->with(['product:id,part_number,name', 'rack:id,code'])->get();
                $oldSnap = $this->cycleItemSnapshot($items);
                $plan = $this->cycleDeltaPlan($oldSnap, $validated['items'], $items);

                foreach ($plan as $row) {
                    $stock = Stock::where('product_id', $row['product_id'])
                        ->where('rack_id', $row['rack_id'])
                        ->lockForUpdate()
                        ->first();

                    $current = $stock?->quantity ?? 0;
                    $result = $current + $row['delta'];

                    if ($result < 0) {
                        $rackLabel = $row['rack_id'] !== null ? 'rak #'.$row['rack_id'] : 'RELAY';
                        throw new \RuntimeException(
                            "Stok tidak cukup untuk dikembalikan: stok {$rackLabel} hanya {$current}, perlu dikurangi ".abs($row['delta'])
                        );
                    }

                    if ($row['delta'] !== 0) {
                        if (! $stock) {
                            $stock = Stock::create([
                                'product_id' => $row['product_id'],
                                'rack_id' => $row['rack_id'],
                                'quantity' => 0,
                            ]);
                        }
                        $stock->quantity = $result;
                        $stock->save();
                    }
                }

                foreach ($validated['items'] as $row) {
                    $item = $items->firstWhere('id', (int) $row['id']);
                    if (! $item) {
                        continue;
                    }
                    $item->update([
                        'quantity' => (int) $row['quantity'],
                        'received_quantity' => (int) $row['received_quantity'],
                        'rack_id' => $row['rack_id'] ?? null,
                        'notes' => $row['notes'] ?? null,
                    ]);
                }

                // Status cycle mengikuti kelengkapan (seperti receive)
                $remaining = $locked->items()->whereRaw('received_quantity < quantity')->count();
                $locked->update([
                    'status' => $remaining === 0 ? 'completed' : 'receiving',
                    'received_at' => $remaining === 0 ? now() : null,
                ]);

                $afterSnap = $this->cycleItemSnapshot(
                    $locked->items()->with(['product:id,part_number,name', 'rack:id,code'])->get()
                );
                [$lines, ] = $this->cycleDeltaLines($plan);

                \App\Models\StockCorrection::create([
                    'correctable_type' => Cycle::class,
                    'correctable_id' => $locked->id,
                    'user_id' => auth()->id(),
                    'reason' => $validated['reason'],
                    'before' => $oldSnap,
                    'after' => $afterSnap,
                    'deltas' => $lines,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Koreksi gagal: '.$e->getMessage());
        }

        try {
            event(new StockChanged(supplierId: $cycle->supplier_id));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('cycles.show', $cycle)
            ->with('success', 'Koreksi cycle diterapkan — stok disesuaikan & tercatat di riwayat.');
    }
}
