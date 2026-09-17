<?php

namespace App\Http\Controllers;

use App\Events\StockChanged;
use App\Models\Product;
use App\Services\ImportExport\Enums\ImportFormat;
use App\Services\ImportExport\Imports\ShoppingHeaderImporter;
use App\Services\ImportExport\Imports\ShoppingImporter;
use App\Services\ImportExport\Imports\ShoppingItemImporter;
use App\Services\ImportExport\Managers\ImportManager;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\Stock;
use App\Http\Controllers\Concerns\AdjustsStock;
use App\Http\Controllers\Concerns\HasPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShoppingController extends Controller
{
    use AdjustsStock;
    use HasPagination;

    public function index(Request $request)
    {
        // Hanya `items_count` yang dipakai tabel — jangan eager-load semua item
        // (frame dengan ratusan/ribuan item bikin payload & memori membengkak).
        $shoppings = Shopping::with(['shoppingLocation', 'shippedBy:id,name'])
            ->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->whereHas('shoppingLocation', fn ($ql) => $ql->where('name', 'like', "%{$s}%")))
            ->latest()
            ->paginate($this->perPage(10))
            ->withQueryString();

        return Inertia::render('Transactions/Shopping/Index', [
            'shoppings' => $shoppings,
            'filters' => $request->only(['status', 'search']),
            'shoppingLocations' => ShoppingLocation::orderBy('name')->get(),
            // Daftar frame draft TIDAK lagi dikirim utuh ke browser: setelah import
            // besar (ribuan frame) payload ini bisa puluhan MB → worker Octane
            // kehabisan memori → 502 saat halaman di-refresh. Modal "Kirim Massal"
            // mencari frame lewat endpoint shoppings/draft-frames (server-side).
            'draftFrameCount' => Shopping::where('status', 'draft')->whereNotNull('frame_number')->count(),
            'openItemImport' => $request->query('import') === 'items',
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

    public function importPreview(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            // Lokasi tujuan OPSIONAL: data import dari TAM tidak punya lokasi/line.
            // Lokasi & frame number adalah input tambahan WMS untuk lookup data TAM.
            'shopping_location_id' => 'nullable|exists:shopping_locations,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = app(ImportManager::class)->preview(
            new ShoppingImporter(isset($validated['shopping_location_id']) ? (int) $validated['shopping_location_id'] : null),
            $request->file('file')
        );

        return response()->json($result);
    }

    public function import(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            // Lokasi tujuan OPSIONAL — lihat importPreview().
            'shopping_location_id' => 'nullable|exists:shopping_locations,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $importLog = app(ImportManager::class)->start(
            new ShoppingImporter(isset($validated['shopping_location_id']) ? (int) $validated['shopping_location_id'] : null),
            $request->file('file'),
            $request->input('column_mapping'),
            auth()->id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function importTemplate(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $format = ImportFormat::from($request->query('format', 'xlsx'));

        return (new ShoppingImporter(0))->downloadTemplate($format);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Alur 2 langkah: (1) input HEADER line + frame number, (2) import BARANG.
    // Alur import gabungan lama di atas TETAP ADA — pusat/TAM bisa mengubah
    // urutan kapan saja, jadi jangan hapus salah satu alur.
    // ─────────────────────────────────────────────────────────────────────

    /** Langkah 1 — halaman input header (line + frame number) tanpa item. */
    public function headerCreate()
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        return Inertia::render('Transactions/Shopping/Headers', [
            'shoppingLocations' => ShoppingLocation::orderBy('name')->get(['id', 'name', 'barcode']),
            'draftFrameCount' => Shopping::where('status', 'draft')->whereNotNull('frame_number')->count(),
        ]);
    }

    /**
     * Langkah 1 — simpan banyak header sekaligus (input cepat, tanpa file).
     * Frame yang sudah ada di sistem dilewati dan dilaporkan, bukan error 500.
     */
    public function headerStore(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        // Baris kosong (sisa form) diabaikan supaya operator tidak perlu
        // menghapus baris yang tidak terpakai satu per satu.
        $rows = collect($request->input('rows', []))
            ->filter(fn ($row) => trim((string) ($row['frame_number'] ?? '')) !== '')
            ->values()
            ->all();

        $request->merge(['rows' => $rows]);

        $validated = $request->validate([
            'shopping_date' => 'nullable|date',
            'rows' => 'required|array|min:1|max:500',
            'rows.*.frame_number' => 'required|string|max:100',
            'rows.*.shopping_location_id' => 'nullable|exists:shopping_locations,id',
            'rows.*.is_cripple' => 'nullable|boolean',
        ], [], [
            'rows' => 'baris',
            'rows.*.frame_number' => 'frame number',
            'rows.*.shopping_location_id' => 'line/lokasi',
        ]);

        $date = isset($validated['shopping_date']) ? Carbon::parse($validated['shopping_date']) : now();
        $created = 0;
        $duplicates = [];
        $seen = [];

        DB::transaction(function () use ($validated, $date, &$created, &$duplicates, &$seen) {
            foreach ($validated['rows'] as $row) {
                $frame = trim((string) $row['frame_number']);
                $key = mb_strtoupper($frame);

                // Duplikat di dalam form atau sudah ada di DB → dilewati.
                $exists = isset($seen[$key])
                    || Shopping::where('frame_number', $frame)
                        ->orWhereRaw('UPPER(frame_number) = ?', [$key])
                        ->exists();

                if ($exists) {
                    $duplicates[] = $frame;
                    continue;
                }

                Shopping::create([
                    'shopping_location_id' => $row['shopping_location_id'] ?? null,
                    'shopping_date' => $date,
                    'status' => 'draft',
                    'is_cripple' => (bool) ($row['is_cripple'] ?? false),
                    'frame_number' => $frame,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $seen[$key] = true;
                $created++;
            }
        });

        $message = "{$created} header frame ditambahkan (draft, belum ada barang).";
        if ($duplicates !== []) {
            $shown = array_slice($duplicates, 0, 10);
            $message .= ' Dilewati karena sudah ada (' . count($duplicates) . '): ' . implode(', ', $shown);
            if (count($duplicates) > count($shown)) {
                $message .= ', …';
            }
        }

        return redirect()
            ->route('shoppings.headers.create')
            ->with($created > 0 ? 'success' : 'warning', $message);
    }

    /** Langkah 2 — preview file barang (Frame Number + Part Number + Quantity). */
    public function itemImportPreview(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            'shopping_location_id' => 'nullable|exists:shopping_locations,id',
            'auto_create_frame' => 'nullable|boolean',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = app(ImportManager::class)->preview(
            $this->itemImporter($validated),
            $request->file('file')
        );

        return response()->json($result);
    }

    /** Langkah 2 — jalankan import barang (job queue). */
    public function itemImport(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $validated = $request->validate([
            'shopping_location_id' => 'nullable|exists:shopping_locations,id',
            'auto_create_frame' => 'nullable|boolean',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $importLog = app(ImportManager::class)->start(
            $this->itemImporter($validated),
            $request->file('file'),
            $request->input('column_mapping'),
            auth()->id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function itemImportTemplate(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $format = ImportFormat::from($request->query('format', 'xlsx'));

        return (new ShoppingItemImporter())->downloadTemplate($format);
    }

    /**
     * Import file HEADER (Line + Frame Number) — DISIAPKAN tapi tombolnya masih
     * disembunyikan di UI (lihat SHOW_HEADER_IMPORT pada halaman Headers).
     * Dipakai kalau pusat/TAM memutuskan mengirim file header, bukan input manual.
     */
    public function headerImportPreview(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        return response()->json(
            app(ImportManager::class)->preview(new ShoppingHeaderImporter(), $request->file('file'))
        );
    }

    public function headerImport(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $importLog = app(ImportManager::class)->start(
            new ShoppingHeaderImporter(),
            $request->file('file'),
            $request->input('column_mapping'),
            auth()->id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function headerImportTemplate(Request $request)
    {
        abort_unless(auth()->user()->can('create shoppings'), 403);

        $format = ImportFormat::from($request->query('format', 'xlsx'));

        return (new ShoppingHeaderImporter())->downloadTemplate($format);
    }

    private function itemImporter(array $validated): ShoppingItemImporter
    {
        return new ShoppingItemImporter(
            isset($validated['shopping_location_id']) ? (int) $validated['shopping_location_id'] : null,
            (bool) ($validated['auto_create_frame'] ?? false),
        );
    }

    /**
     * Pencarian frame draft untuk modal "Kirim Massal" (search/scan).
     * Server-side + limit → payload halaman index tetap kecil walau draft
     * sudah puluhan ribu frame.
     */
    public function draftFrames(Request $request)
    {
        abort_unless(auth()->user()->can('view shoppings'), 403);

        $search = trim((string) $request->query('search', ''));
        $frame = trim((string) $request->query('frame', ''));
        $limit = min(max((int) $request->query('limit', 30), 1), 100);

        $base = Shopping::query()->where('status', 'draft')->whereNotNull('frame_number');

        $query = (clone $base)->with('shoppingLocation:id,name')->orderBy('frame_number');

        if ($frame !== '') {
            $query->where('frame_number', $frame);
        } elseif ($search !== '') {
            $query->where('frame_number', 'like', '%' . $search . '%');
        }

        $rows = $query->limit($limit + 1)->get(['id', 'frame_number', 'shopping_location_id', 'is_cripple']);

        return response()->json([
            'data' => $rows->take($limit)->values(),
            'has_more' => $rows->count() > $limit,
            'total' => (clone $base)->count(),
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
            'is_cripple' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $shopping = Shopping::create([
            'shopping_location_id' => $validated['shopping_location_id'],
            'shopping_date' => $validated['shopping_date'],
            'status' => 'draft',
            'is_cripple' => (bool) ($validated['is_cripple'] ?? false),
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
            'shopping' => $shopping->load('items.product.vehicleModel', 'items.rack', 'shoppingLocation', 'shippedBy'),
            'corrections' => $shopping->corrections()->with('user:id,name')->orderByDesc('id')->get(),
            'canCorrect' => auth()->user()->can('correct shoppings'),
        ]);
    }

    public function edit(Shopping $shopping)
    {
        abort_unless(auth()->user()->can('edit shoppings'), 403);

        // Shopping sudah dikirim (shipped/cripple): hanya user dengan permission
        // "correct shoppings" (superadmin) yang boleh membuka dalam MODE KOREKSI.
        $correction = $shopping->status !== 'draft';
        if ($correction) {
            abort_unless(auth()->user()->can('correct shoppings'), 403);
        }

        return Inertia::render('Transactions/Shopping/Edit', [
            'shopping'           => $shopping->load('items.product.vehicleModel', 'shoppingLocation'),
            'products'           => Product::with(['vehicleModel', 'stocks', 'supplier'])->where('is_active', true)->orderBy('name')->get(),
            'racks'              => Rack::orderBy('zone')->orderBy('code')->get(),
            'shoppingLocations'  => ShoppingLocation::orderBy('name')->get(),
            'correction'         => $correction,
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
            'is_cripple' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $shopping->update([
            'shopping_location_id' => $validated['shopping_location_id'],
            'shopping_date' => $validated['shopping_date'],
            'is_cripple' => (bool) ($validated['is_cripple'] ?? false),
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

        $result = $this->shipSingle($shopping);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        try {
            event(new StockChanged());
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $shopping->fresh()->is_cripple
            ? 'Shopping diproses sebagai CRIPPLE (part tidak lengkap). Stok dikurangi.'
            : 'Shopping diproses. Stok dikurangi.';

        return redirect()->route('shoppings.show', $shopping)->with('success', $message);
    }

    /**
     * Proses ship SATU shopping (dipakai ship() dan bulkShip()).
     * - Opsional mengisi shopping_location_id yang masih kosong (bulk action).
     * - Kurangi stok, set status shipped/cripple sesuai is_cripple.
     *
     * @return array{ok: bool, error?: string}
     */
    private function shipSingle(Shopping $shopping, ?int $fillLocationId = null): array
    {
        return DB::transaction(function () use ($shopping, $fillLocationId) {
            $lockedShopping = Shopping::where('id', $shopping->id)->lockForUpdate()->firstOrFail();

            if ($lockedShopping->status !== 'draft') {
                return ['ok' => false, 'error' => 'Status bukan draft.'];
            }

            // Bulk action: isi lokasi yang masih kosong (data import TAM)
            if ($fillLocationId && $lockedShopping->shopping_location_id === null) {
                $lockedShopping->update(['shopping_location_id' => $fillLocationId]);
            }

            $items = $lockedShopping->items()->with('product', 'rack')->get();

            if ($items->isEmpty()) {
                return ['ok' => false, 'error' => 'Part tidak lengkap — tidak ada item.'];
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

            $lockedShopping->update([
                // Barang cripple (part tidak lengkap, checklist "Apakah barang ini cripple?")
                // tetap diproses & stok dikurangi, tapi statusnya 'cripple', bukan 'shipped'.
                'status' => $lockedShopping->is_cripple ? 'cripple' : 'shipped',
                'shipped_by' => auth()->id(),
                'shipped_at' => now(),
            ]);

            return ['ok' => true];
        });
    }

    /**
     * Bulk ship — proses banyak shopping draft sekaligus.
     * Body: { ids: int[], shopping_location_id?: int|null }
     * Lokasi yang dipilih otomatis mengisi shopping yang lokasinya masih kosong
     * (data import dari TAM), lalu semua diproses.
     */
    public function bulkShip(Request $request)
    {
        abort_unless(auth()->user()->can('ship shoppings'), 403);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:shoppings,id',
            'shopping_location_id' => 'nullable|exists:shopping_locations,id',
        ]);

        $locationId = $validated['shopping_location_id'] ?? null;
        $success = 0;
        $failures = [];

        foreach ($validated['ids'] as $id) {
            $shopping = Shopping::find($id);

            if (! $shopping) {
                $failures[] = ['id' => $id, 'reason' => 'Shopping tidak ditemukan.'];

                continue;
            }

            $result = $this->shipSingle($shopping, $locationId);

            if ($result['ok']) {
                $success++;
            } else {
                $failures[] = [
                    'id' => $id,
                    'frame' => $shopping->frame_number,
                    'reason' => $result['error'],
                ];
            }
        }

        if ($success > 0) {
            try {
                event(new StockChanged());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $msg = "{$success} shopping berhasil dikirim.";

        if (! empty($failures)) {
            $detail = collect($failures)
                ->map(fn ($f) => ($f['frame'] ?? '#' . $f['id']) . ' — ' . $f['reason'])
                ->implode('; ');
            $msg .= ' Gagal (' . count($failures) . '): ' . $detail;
        }

        return redirect()->route('shoppings.index')->with('success', $msg);
    }

    /**
     * Hapus massal shopping (SUPERADMIN — route group).
     *
     * Semua status boleh dihapus. Untuk shopping yang sudah dikirim
     * (shipped/cripple/completed), stok dikembalikan sebesar qty item —
     * karena saat pengiriman stok dikurangi. Draft tidak menyentuh stok.
     */
    public function bulkDelete(Request $request)
    {
        abort_unless(auth()->user()->can('delete shoppings'), 403);

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $ids = array_map('intval', $validated['ids']);

        $result = DB::transaction(function () use ($ids) {
            $shoppings = Shopping::with('items')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            $deleted = 0;
            $restored = 0;
            $shortage = 0;

            foreach ($shoppings as $shopping) {
                $isShipped = in_array($shopping->status, ['shipped', 'cripple', 'completed'], true);

                if ($isShipped) {
                    foreach ($shopping->items as $item) {
                        $adjust = $this->adjustStock((int) $item->product_id, $item->rack_id, (int) $item->quantity);
                        $restored += max($adjust['applied'], 0);
                        $shortage += $adjust['shortage'];
                    }
                }

                $shopping->delete();
                $deleted++;
            }

            return ['deleted' => $deleted, 'restored' => $restored, 'shortage' => $shortage];
        });

        try {
            event(new StockChanged());
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'deleted' => $result['deleted'],
            'stock_restored' => $result['restored'],
            'stock_shortage' => $result['shortage'],
            'message' => "{$result['deleted']} shopping dihapus"
                . ($result['restored'] > 0 ? ", stok dikembalikan {$result['restored']} pcs" : '')
                . ($result['shortage'] > 0 ? " (peringatan: {$result['shortage']} pcs tidak bisa dikembalikan karena stok tidak cukup)" : '')
                . '.',
        ]);
    }

    // ── KOREKSI shopping yang SUDAH dikirim (khusus permission correct shoppings) ──
    // Prinsip anti-abuse: alasan wajib, delta stok dihitung otomatis dari
    // perbandingan item lama vs baru, atomic + lock, ditolak bila stok kurang,
    // dan setiap koreksi tercatat (stock_corrections + activity log).

    private function validateCorrectionData(Request $request): array
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
            'shopping_location_id' => 'required|exists:shopping_locations,id',
            'shopping_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'frame_number' => 'nullable|string|max:100',
            'is_cripple' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.rack_id' => 'nullable|exists:racks,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        $validated['items'] = ! empty($validated['items'])
            ? $this->mergeDuplicateItems($validated['items'])
            : [];

        return $validated;
    }

    /** Snapshot item shopping untuk log & perhitungan delta. */
    private function shoppingItemSnapshot($items): array
    {
        return $items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'part_number' => $i->product?->part_number ?? '#'.$i->product_id,
            'product_name' => $i->product?->name ?? '',
            'rack_id' => $i->rack_id,
            'rack_code' => $i->rack?->code ?? 'RELAY',
            'quantity' => (int) $i->quantity,
        ])->values()->all();
    }

    /** Rencana delta stok per (produk, rak): positif = stok kembali (refund). */
    private function shoppingDeltaPlan(array $oldSnap, array $newMerged): array
    {
        $delta = [];

        foreach ($oldSnap as $it) {
            $key = $it['product_id'].'|'.($it['rack_id'] ?? 'null');
            $delta[$key] = [
                'product_id' => $it['product_id'],
                'part_number' => $it['part_number'],
                'product_name' => $it['product_name'],
                'rack_id' => $it['rack_id'],
                'rack_code' => $it['rack_code'],
                'delta' => ($delta[$key]['delta'] ?? 0) + $it['quantity'],
            ];
        }

        foreach ($newMerged as $it) {
            $key = $it['product_id'].'|'.($it['rack_id'] ?? 'null');
            if (! isset($delta[$key])) {
                $delta[$key] = [
                    'product_id' => $it['product_id'],
                    'part_number' => '',
                    'product_name' => '',
                    'rack_id' => $it['rack_id'],
                    'rack_code' => '',
                    'delta' => 0,
                ];
            }
            $delta[$key]['delta'] -= (int) $it['quantity'];
        }

        return array_values($delta);
    }

    /** Baris delta siap tampil + daftar error ketersediaan stok. */
    private function shoppingDeltaLines(array $plan): array
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
                'part_number' => $product->part_number ?? $row['part_number'],
                'product_name' => $product->name ?? $row['product_name'],
                'rack_code' => $rackCode,
                'current' => (int) $current,
                'delta' => (int) $row['delta'],
                'result' => $result,
            ];

            if ($result < 0) {
                $errors[] = "Stok tidak cukup: {$product?->name} ({$product?->part_number}) di rak {$rackCode} — tersedia {$current}, dibutuhkan ".abs($row['delta']);
            }
        }

        return [$lines, $errors];
    }

    /** Preview koreksi — hitung dampak stok tanpa mengubah apa pun. */
    public function correctPreview(Request $request, Shopping $shopping)
    {
        abort_unless(auth()->user()->can('correct shoppings'), 403);

        if ($shopping->status === 'draft') {
            throw ValidationException::withMessages(['reason' => 'Shopping draft gunakan Edit biasa, bukan koreksi.']);
        }

        $validated = $this->validateCorrectionData($request);
        $oldSnap = $this->shoppingItemSnapshot($shopping->items()->with(['product:id,part_number,name', 'rack:id,code'])->get());
        $plan = $this->shoppingDeltaPlan($oldSnap, $validated['items']);
        [$lines, $errors] = $this->shoppingDeltaLines($plan);

        return response()->json([
            'lines' => $lines,
            'errors' => $errors,
            'ok' => empty($errors),
        ]);
    }

    /** Terapkan koreksi shopping final secara atomik. */
    public function correct(Request $request, Shopping $shopping)
    {
        abort_unless(auth()->user()->can('correct shoppings'), 403);

        if ($shopping->status === 'draft') {
            return back()->with('error', 'Shopping draft gunakan Edit biasa.');
        }

        $validated = $this->validateCorrectionData($request);

        try {
            DB::transaction(function () use ($validated, $shopping) {
                $locked = Shopping::where('id', $shopping->id)->lockForUpdate()->firstOrFail();

                $oldSnap = $this->shoppingItemSnapshot(
                    $locked->items()->with(['product:id,part_number,name', 'rack:id,code'])->get()
                );
                $newMerged = $validated['items'];
                $plan = $this->shoppingDeltaPlan($oldSnap, $newMerged);

                foreach ($plan as $row) {
                    $stock = Stock::where('product_id', $row['product_id'])
                        ->where('rack_id', $row['rack_id'])
                        ->lockForUpdate()
                        ->first();

                    $current = $stock?->quantity ?? 0;
                    $result = $current + $row['delta'];

                    if ($result < 0) {
                        throw new \RuntimeException(
                            "Stok tidak cukup: {$row['part_number']} di rak {$row['rack_code']} — tersedia {$current}, butuh ".abs($row['delta'])
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

                $locked->update([
                    'shopping_location_id' => $validated['shopping_location_id'],
                    'shopping_date' => $validated['shopping_date'],
                    'notes' => $validated['notes'] ?? null,
                    'frame_number' => $validated['frame_number'] ?? null,
                    'is_cripple' => (bool) ($validated['is_cripple'] ?? false),
                    // Status tetap final; label cripple mengikuti flag terbaru
                    'status' => (bool) ($validated['is_cripple'] ?? false) ? 'cripple' : 'shipped',
                ]);

                $locked->items()->delete();
                foreach ($newMerged as $item) {
                    $locked->items()->create([
                        'product_id' => $item['product_id'],
                        'rack_id' => $item['rack_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                $afterSnap = $this->shoppingItemSnapshot(
                    $locked->items()->with(['product:id,part_number,name', 'rack:id,code'])->get()
                );
                [$lines, ] = $this->shoppingDeltaLines($plan);

                \App\Models\StockCorrection::create([
                    'correctable_type' => Shopping::class,
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
            event(new StockChanged());
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('shoppings.show', $shopping)
            ->with('success', 'Koreksi shopping diterapkan — stok disesuaikan & tercatat di riwayat.');
    }
}
