<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPagination;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\ShoppingItem;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\Stock\StockLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Inventori stok (Transactions > Stocks).
 *
 * Daftar ini per baris (produk × rak/RELAY), jadi satu produk bisa muncul
 * beberapa kali kalau stoknya tersebar. Supaya tetap gampang dicari:
 *
 *  - `search` bebas: part number, nama produk, deskripsi, supplier, model
 *    kendaraan (brand/nama/suffix), kode rak, zona, plus kata "relay"/"tanpa rak".
 *    Beberapa kata dipisah spasi = harus cocok SEMUA (mis. "visor avanza").
 *  - filter: rak, zona, supplier, status (ada rak / RELAY / menipis / kosong),
 *    produk tertentu (`product_id`, mis. dari halaman produk).
 *  - urut: qty terbesar/kecil, part number, nama, rak, terakhir diubah.
 *  - ringkasan (summary) dihitung di SQL untuk SELURUH hasil filter, bukan
 *    hanya halaman yang tampil — jadi tidak ada koleksi tak terbatas di memori.
 */
class StockController extends Controller
{
    use HasPagination;

    /** Nilai `status` yang dikenali. */
    public const STATUSES = ['rack', 'relay', 'low', 'zero', 'available'];

    /** Nilai `sort` yang dikenali. */
    public const SORTS = ['qty_desc', 'qty_asc', 'part_asc', 'name_asc', 'rack_asc', 'updated_desc'];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $base = $this->filteredQuery($filters);

        $perPage = $this->perPage(25, $request);

        $stocks = $base->clone()
            ->with(['product.supplier', 'product.vehicleModel', 'rack'])
            ->orderBy(...$this->orderFor($filters['sort']))
            ->paginate($perPage)
            ->withQueryString();

        $this->attachMovementTotals($stocks);

        $activeProduct = $filters['product_id']
            ? Product::whereKey($filters['product_id'])->first(['id', 'part_number', 'name'])
            : null;

        return Inertia::render('Transactions/Stocks/Index', [
            'stocks' => $stocks,
            'summary' => $this->summary($base),
            'racks' => Rack::orderBy('zone')->orderBy('code')->get(['id', 'code', 'zone']),
            'zones' => Rack::select('zone')->whereNotNull('zone')->distinct()->orderBy('zone')->pluck('zone'),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'activeProduct' => $activeProduct,
            'filters' => $filters,
            'sortOptions' => self::SORTS,
        ]);
    }

    /** @return array{search:string, rack_id:int|null, zone:string, supplier_id:int|null, status:string, sort:string, product_id:int|null} */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $sort = (string) $request->query('sort', 'qty_desc');

        return [
            'search' => trim((string) $request->query('search', '')),
            'rack_id' => $request->integer('rack_id') ?: null,
            'zone' => trim((string) $request->query('zone', '')),
            'supplier_id' => $request->integer('supplier_id') ?: null,
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'qty_desc',
            'product_id' => $request->integer('product_id') ?: null,
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        return Stock::query()
            ->when($filters['search'] !== '', fn ($q) => $this->applySearch($q, $filters['search']))
            ->when($filters['rack_id'], fn ($q, $id) => $q->where('rack_id', $id))
            ->when($filters['zone'] !== '', fn ($q) => $q->whereHas('rack', fn ($r) => $r->where('zone', $filters['zone'])))
            ->when($filters['supplier_id'], fn ($q, $id) => $q->whereHas('product', fn ($p) => $p->where('supplier_id', $id)))
            ->when($filters['product_id'], fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['status'] !== '', fn ($q) => $this->applyStatus($q, $filters['status']));
    }

    /**
     * Pencarian bebas multi-kata. Setiap kata harus cocok di salah satu kolom;
     * antar kata bersifat DAN (mis. "visor avanza" hanya baris yang cocok dua-duanya).
     */
    private function applySearch(Builder $query, string $search): void
    {
        $terms = array_slice(preg_split('/\s+/', $search) ?: [], 0, 4);

        foreach ($terms as $term) {
            $term = trim($term);

            if ($term === '') {
                continue;
            }

            $like = '%' . $term . '%';
            $isRelayWord = in_array(mb_strtolower($term), ['relay', 'overflow', 'tanpa', 'nonrak'], true);

            $query->where(function ($q) use ($like, $isRelayWord) {
                $q->whereHas('product', fn ($p) => $p
                        ->where('part_number', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('description', 'like', $like))
                    ->orWhereHas('product.supplier', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhereHas('product.vehicleModel', fn ($v) => $v
                        ->where('brand', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('suffix', 'like', $like))
                    ->orWhereHas('rack', fn ($r) => $r
                        ->where('code', 'like', $like)
                        ->orWhere('zone', 'like', $like));

                // Kata "relay" / "tanpa rak" → tampilkan baris tanpa rak.
                if ($isRelayWord) {
                    $q->orWhereNull('rack_id');
                }
            });
        }
    }

    private function applyStatus(Builder $query, string $status): void
    {
        match ($status) {
            'rack' => $query->whereNotNull('rack_id'),
            'relay' => $query->whereNull('rack_id'),
            'zero' => $query->where('quantity', 0),
            'available' => $query->where('quantity', '>', 0),
            'low' => $query
                ->where('quantity', '>', 0)
                ->whereHas('product', fn ($p) => $p
                    ->whereNotNull('min_stock')
                    ->whereColumn('products.min_stock', '>', 'stocks.quantity')),
            default => null,
        };
    }

    /**
     * Urutan: [kolom, arah]. Kolom relasi memakai subquery (tanpa join) supaya
     * baris RELAY (rack_id NULL) tidak hilang dari hasil.
     *
     * @return array{0:mixed, 1:string}
     */
    private function orderFor(string $sort): array
    {
        return match ($sort) {
            'qty_asc' => ['quantity', 'asc'],
            'part_asc' => [Product::select('part_number')->whereColumn('products.id', 'stocks.product_id'), 'asc'],
            'name_asc' => [Product::select('name')->whereColumn('products.id', 'stocks.product_id'), 'asc'],
            'rack_asc' => [Rack::select('code')->whereColumn('racks.id', 'stocks.rack_id'), 'asc'],
            'updated_desc' => ['updated_at', 'desc'],
            default => ['quantity', 'desc'],
        };
    }

    /**
     * Ringkasan hasil filter (SQL agregat, aman untuk data besar).
     *
     * @return array{rows:int, quantity:int, products:int, relay_rows:int, relay_quantity:int, low_rows:int}
     */
    private function summary(Builder $base): array
    {
        $row = $base->clone()
            ->selectRaw('COUNT(*) AS rows_count')
            ->selectRaw('COALESCE(SUM(quantity), 0) AS quantity_total')
            ->selectRaw('COUNT(DISTINCT product_id) AS products_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN rack_id IS NULL THEN 1 ELSE 0 END), 0) AS relay_rows')
            ->selectRaw('COALESCE(SUM(CASE WHEN rack_id IS NULL THEN quantity ELSE 0 END), 0) AS relay_quantity')
            ->first();

        $lowRows = $base->clone()
            ->where('quantity', '>', 0)
            ->whereHas('product', fn ($p) => $p
                ->whereNotNull('min_stock')
                ->whereColumn('products.min_stock', '>', 'stocks.quantity'))
            ->count();

        return [
            'rows' => (int) ($row->rows_count ?? 0),
            'quantity' => (int) ($row->quantity_total ?? 0),
            'products' => (int) ($row->products_count ?? 0),
            'relay_rows' => (int) ($row->relay_rows ?? 0),
            'relay_quantity' => (int) ($row->relay_quantity ?? 0),
            'low_rows' => (int) $lowRows,
        ];
    }

    /**
     * Total masuk/keluar per baris (produk × rak) untuk halaman yang tampil.
     *
     * - Masuk  = Σ `cycle_items.received_quantity` (semua status; menerima
     *   sebagian pun tetap terhitung, sama seperti buku besar stok).
     * - Keluar = Σ `shopping_items.quantity` untuk shopping yang sudah dikirim
     *   (shipped / cripple / completed — sama dengan `StockLedger::OUT_STATUSES`).
     */
    private function attachMovementTotals($stocks): void
    {
        $pairs = $stocks->getCollection()->map(fn ($stock) => [
            'product_id' => $stock->product_id,
            'rack_id' => $stock->rack_id,
        ])->unique(fn ($p) => $p['product_id'] . '-' . ($p['rack_id'] ?? 'null'));

        $productIds = $pairs->pluck('product_id')->unique()->values();

        $totalIn = CycleItem::query()
            ->selectRaw('product_id, rack_id, COALESCE(SUM(received_quantity), 0) AS total_in')
            ->where('received_quantity', '>', 0)
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'rack_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id . '-' . ($row->rack_id ?? 'null') => (int) $row->total_in,
            ]);

        $totalOut = ShoppingItem::query()
            ->selectRaw('product_id, rack_id, COALESCE(SUM(quantity), 0) AS total_out')
            ->whereHas('shopping', fn ($q) => $q->whereIn('status', StockLedger::OUT_STATUSES))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'rack_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id . '-' . ($row->rack_id ?? 'null') => (int) $row->total_out,
            ]);

        $stocks->getCollection()->transform(function ($stock) use ($totalIn, $totalOut) {
            $key = $stock->product_id . '-' . ($stock->rack_id ?? 'null');
            $stock->total_in = $totalIn[$key] ?? 0;
            $stock->total_out = $totalOut[$key] ?? 0;

            return $stock;
        });
    }
}
