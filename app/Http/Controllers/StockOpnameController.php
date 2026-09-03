<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\ImportExport\Support\RawFileImport;
use App\Services\StockOpname\StockOpnameTemplateExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class StockOpnameController extends Controller
{
    /** Token yang dianggap "tanpa rak / relay" di kolom RAK file excel. */
    private const RELAY_TOKENS = ['', 'relay', 'overflow', '-', '—', 'non rak', 'tanpa rak'];

    /**
     * Riwayat stock opname.
     */
    public function index()
    {
        $opnames = StockOpname::with('creator:id,name')
            ->latest('id')
            ->paginate(15);

        return Inertia::render('Transactions/StockOpname/Index', [
            'opnames' => $opnames,
            'zones' => Rack::select('zone')->distinct()->orderBy('zone')->pluck('zone'),
            'racks' => Rack::orderBy('zone')->orderBy('code')->get(['id', 'code', 'zone']),
        ]);
    }

    /**
     * Unduh template stock opname terisi stok sistem saat ini.
     * Filter opsional: zone / rack_id. Kolom SAP = qty sistem.
     */
    public function template(Request $request)
    {
        $validated = $request->validate([
            'zone' => 'nullable|string|max:50',
            'rack_id' => 'nullable|exists:racks,id',
        ]);

        $stocks = Stock::query()
            ->with(['product:id,part_number,name', 'rack:id,code,zone'])
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->when($validated['rack_id'] ?? null, fn ($q, $id) => $q->where('rack_id', $id))
            ->when(
                ! empty($validated['zone']) && empty($validated['rack_id']),
                fn ($q) => $q->whereHas('rack', fn ($qr) => $qr->where('zone', $validated['zone']))
            )
            ->orderBy(Rack::select('code')->whereColumn('racks.id', 'stocks.rack_id'))
            ->orderBy(Product::select('part_number')->whereColumn('products.id', 'stocks.product_id'))
            ->get();

        $rows = $stocks->map(fn (Stock $s) => [
            $s->product?->part_number ?? '',
            $s->product?->name ?? '',
            $s->rack?->code ?? 'RELAY',
            (int) $s->quantity,
        ])->all();

        return Excel::download(
            new StockOpnameTemplateExport($rows),
            'Template-Stock-Opname-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /**
     * Baca file hasil opname → daftar baris + status selisih (preview).
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $rows = $this->resolveRows($this->parseUploadedFile($request->file('file')));

        return response()->json([
            'rows' => array_map(fn ($r) => $this->clientRow($r), $rows),
            'summary' => $this->summarize($rows),
        ]);
    }

    /**
     * Terapkan hasil opname (sesuaikan stok + catat riwayat).
     * Baris di-resolve ulang di server — tidak percaya payload klien.
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:5000',
            'rows.*.part_number' => 'required|string|max:100',
            'rows.*.rack_code' => 'nullable|string|max:100',
            'rows.*.actual_qty' => 'required|integer|min:0|max:1000000',
        ]);

        $parsed = array_map(fn ($r) => [
            'part_number' => trim($r['part_number']),
            'rack_code' => trim((string) ($r['rack_code'] ?? '')),
            'actual_qty' => (int) $r['actual_qty'],
        ], $validated['rows']);

        $resolved = $this->resolveRows($parsed);

        // Baris yang benar-benar diterapkan: bukan dilewati & stok ada (same/diff)
        // atau barang baru dgn qty fisik > 0.
        $applicable = array_values(array_filter(
            $resolved,
            fn ($r) => ! $r['_skipApply'] && in_array($r['status'], ['same', 'diff', 'new'], true)
        ));

        if (empty($applicable)) {
            throw ValidationException::withMessages(['rows' => 'Tidak ada baris yang bisa diterapkan (semua dilewati/tidak dikenal).']);
        }

        $result = DB::transaction(function () use ($applicable) {
            $today = now()->toDateString();
            $seq = StockOpname::whereDate('opname_date', $today)->count() + 1;
            $opname = StockOpname::create([
                'code' => 'OP-' . str_replace('-', '', $today) . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
                'opname_date' => $today,
                'total_items' => count($applicable),
                'created_by' => auth()->id(),
            ]);

            $diffCount = 0;
            $newCount = 0;

            foreach ($applicable as $row) {
                /** @var Stock|null $existing */
                $existing = $row['_stock'];

                if ($existing) {
                    $stock = Stock::where('id', $existing->id)->lockForUpdate()->firstOrFail();
                    $sapQty = (int) $stock->quantity;
                    $stock->quantity = $row['actual_qty'];
                    $stock->save();
                } else {
                    // Barang ada di fisik (qty > 0) tapi belum ada baris stok → buat
                    $stock = Stock::create([
                        'product_id' => $row['product_id'],
                        'rack_id' => $row['rack_id'],
                        'quantity' => $row['actual_qty'],
                    ]);
                    $sapQty = 0;
                    $newCount++;
                }

                $diff = (int) $row['actual_qty'] - $sapQty;
                if ($diff !== 0) {
                    $diffCount++;
                }

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $row['product_id'],
                    'rack_id' => $row['rack_id'],
                    'part_number' => $row['part_number'],
                    'product_name' => $row['product_name'],
                    'rack_code' => $row['rack_label'] === 'RELAY' ? null : $row['rack_label'],
                    'sap_qty' => $sapQty,
                    'actual_qty' => $row['actual_qty'],
                    'diff' => $diff,
                ]);
            }

            $opname->update([
                'diff_items' => $diffCount,
                'new_items' => $newCount,
            ]);

            return ['opname' => $opname, 'diff_items' => $diffCount, 'new_items' => $newCount];
        });

        try {
            event(new \App\Events\StockChanged());
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'code' => $result['opname']->code,
            'applied' => count($applicable),
            'diff_items' => $result['diff_items'],
            'new_items' => $result['new_items'],
        ]);
    }

    /**
     * Detail item sebuah opname (untuk modal riwayat).
     */
    public function items(StockOpname $stockOpname)
    {
        $items = $stockOpname->items()
            ->orderBy('rack_code')
            ->orderBy('part_number')
            ->get();

        return response()->json([
            'opname' => [
                'id' => $stockOpname->id,
                'code' => $stockOpname->code,
                'opname_date' => $stockOpname->opname_date->format('Y-m-d'),
                'creator' => $stockOpname->creator?->name ?? '-',
                'total_items' => $stockOpname->total_items,
                'diff_items' => $stockOpname->diff_items,
                'new_items' => $stockOpname->new_items,
            ],
            'items' => $items->map(fn (StockOpnameItem $i) => [
                'id' => $i->id,
                'part_number' => $i->part_number,
                'product_name' => $i->product_name,
                'rack_code' => $i->rack_code ?? 'RELAY',
                'sap_qty' => $i->sap_qty,
                'actual_qty' => $i->actual_qty,
                'diff' => $i->diff,
            ]),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Simpan file upload sementara & baca sebagai baris excel/csv.
     *
     * @return array<int, array{part_number:string, rack_code:string, actual_qty:int|null}>
     */
    private function parseUploadedFile($file): array
    {
        $path = $file->storeAs('imports/temp', uniqid('opname-', true) . '.' . $file->getClientOriginalExtension());

        try {
            $sheets = Excel::toArray(new RawFileImport(), Storage::path($path));
        } finally {
            Storage::delete($path);
        }

        $rows = $sheets[0] ?? [];
        if (empty($rows)) {
            throw ValidationException::withMessages(['file' => 'File kosong.']);
        }

        $header = array_map(fn ($c) => strtolower(preg_replace('/[^a-z0-9]/', '', (string) $c)), $rows[0]);
        $colPart = array_search('partno', $header, true);
        $colRak = array_search('rak', $header, true);
        $colActual = array_search('qtyopname', $header, true);

        if ($colPart === false) {
            throw ValidationException::withMessages(['file' => 'Kolom "Part No" tidak ditemukan di header file.']);
        }
        if ($colActual === false) {
            throw ValidationException::withMessages(['file' => 'Kolom "Qty Opname" tidak ditemukan — gunakan template hasil unduhan aplikasi.']);
        }
        if ($colRak === false) {
            $colRak = null;
        }

        $out = [];
        foreach (array_slice($rows, 1) as $row) {
            $part = $this->cellText($row, $colPart);
            $rack = $colRak !== null ? $this->cellText($row, $colRak) : '';
            $actualRaw = $this->cellText($row, $colActual);

            if ($part === '' && $rack === '' && $actualRaw === '') {
                continue; // baris kosong
            }

            $actual = null;
            if ($actualRaw !== '' && is_numeric($actualRaw)) {
                $actual = (int) (float) $actualRaw;
            }

            $out[] = [
                'part_number' => $part,
                'rack_code' => $rack,
                'actual_qty' => $actual,
            ];
        }

        if (empty($out)) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris data di file.']);
        }

        return $out;
    }

    /**
     * Cocokkan baris ke produk & baris stok sistem, hitung status/selisih.
     *
     * @return array<int, array>
     */
    private function resolveRows(array $parsedRows): array
    {
        $partNumbers = array_values(array_unique(array_filter(array_column($parsedRows, 'part_number'))));
        $products = Product::whereIn('part_number', $partNumbers)->get()->keyBy('part_number');

        $stockRows = collect();
        if ($products->isNotEmpty()) {
            $stockRows = Stock::whereIn('product_id', $products->pluck('id'))
                ->with('rack:id,code')
                ->get();
        }
        $stocksByKey = $stockRows->keyBy(fn ($s) => $s->product_id . '|' . ($s->rack_id ?? 'null'));

        $racksByCode = Rack::get(['id', 'code'])->mapWithKeys(
            fn (Rack $r) => [strtoupper(trim($r->code)) => $r]
        );

        $result = [];

        foreach ($parsedRows as $row) {
            $product = $products->get($row['part_number']);

            if (! $product) {
                $result[] = $this->rowOut($row, null, null, 0, 'not_found', 'Part number tidak dikenal di sistem');
                continue;
            }

            if ($row['actual_qty'] === null) {
                $result[] = $this->rowOut($row, $product, null, 0, 'invalid', 'Qty Opname kosong / tidak valid');
                continue;
            }

            [$rack, $rackLabel] = $this->resolveRack($row['rack_code'], $racksByCode);

            if ($rackLabel === 'UNKNOWN') {
                $result[] = $this->rowOut($row, $product, null, 0, 'invalid', 'Kode rak tidak dikenal di sistem');
                continue;
            }

            $key = $product->id . '|' . ($rack?->id ?? 'null');
            $stock = $stocksByKey->get($key);

            if (! $stock) {
                if ($row['actual_qty'] === 0) {
                    // Tidak ada baris stok & hasil hitung 0 → tidak ada perubahan
                    $result[] = $this->rowOut($row, $product, null, 0, 'same', 'Tidak ada stok tercatat & opname 0 (dilewati)', true);
                } else {
                    $result[] = $this->rowOut($row, $product, null, 0, 'new', 'Ada di fisik tapi belum ada baris stok — akan dibuat');
                }
                continue;
            }

            $systemQty = (int) $stock->quantity;
            $diff = $row['actual_qty'] - $systemQty;

            $result[] = $this->rowOut(
                $row,
                $product,
                $stock,
                $systemQty,
                $diff === 0 ? 'same' : 'diff',
                $diff === 0 ? '' : ($diff > 0 ? "Stok naik {$diff}" : "Stok turun " . abs($diff))
            );
        }

        return $result;
    }

    /**
     * Baris hasil resolve — memuat kunci internal (_stock, dst) yang TIDAK
     * dikirim ke klien (dibersihkan oleh clientRow()).
     */
    private function rowOut(
        array $row,
        ?Product $product,
        ?Stock $stock,
        int $sapQty,
        string $status,
        string $message = '',
        bool $skipApply = false,
    ): array {
        $rackLabel = $this->rackLabelOf($row['rack_code']);

        return [
            'part_number' => $row['part_number'],
            'product_name' => $product?->name ?? '-',
            'rack_code' => $rackLabel,
            'sap_qty' => $sapQty,
            'actual_qty' => $row['actual_qty'] ?? 0,
            'diff' => $product && $stock ? (int) $row['actual_qty'] - (int) $stock->quantity : 0,
            'status' => $status,
            'message' => $message,
            '_skipApply' => $skipApply,
            '_stock' => $stock,
            'product_id' => $product?->id,
            'rack_id' => $stock?->rack_id ?? $this->rackIdOf($row['rack_code']),
            'rack_label' => $rackLabel,
        ];
    }

    /** Representasi baris untuk dikirim ke klien (tanpa kunci internal). */
    private function clientRow(array $row): array
    {
        return [
            'part_number' => $row['part_number'],
            'product_name' => $row['product_name'],
            'rack_code' => $row['rack_code'],
            'sap_qty' => $row['sap_qty'],
            'actual_qty' => $row['actual_qty'],
            'diff' => $row['diff'],
            'status' => $row['status'],
            'message' => $row['message'],
            'skip' => (bool) $row['_skipApply'],
        ];
    }

    private function summarize(array $rows): array
    {
        return [
            'total' => count($rows),
            'diff' => count(array_filter($rows, fn ($r) => $r['status'] === 'diff')),
            'same' => count(array_filter($rows, fn ($r) => $r['status'] === 'same')),
            'new' => count(array_filter($rows, fn ($r) => $r['status'] === 'new')),
            'skipped' => count(array_filter($rows, fn ($r) => in_array($r['status'], ['not_found', 'invalid'], true) || $r['_skipApply'])),
        ];
    }

    /** @return array{0:Rack|null, 1:string} [rack, label_display] */
    private function resolveRack(string $raw, $racksByCode): array
    {
        if ($raw === '' || in_array(strtolower($raw), self::RELAY_TOKENS, true)) {
            return [null, 'RELAY'];
        }

        $rack = $racksByCode->get(strtoupper(trim($raw)));

        if (! $rack) {
            return [null, 'UNKNOWN'];
        }

        return [$rack, $rack->code];
    }

    private function rackLabelOf(string $raw): string
    {
        return $raw === '' || in_array(strtolower($raw), self::RELAY_TOKENS, true) ? 'RELAY' : strtoupper(trim($raw));
    }

    private function rackIdOf(string $raw): ?int
    {
        if ($raw === '' || in_array(strtolower($raw), self::RELAY_TOKENS, true)) {
            return null;
        }

        return Rack::where('code', strtoupper(trim($raw)))->value('id');
    }

    private function cellText(array $row, int $index): string
    {
        $value = $row[$index] ?? null;

        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }
}
