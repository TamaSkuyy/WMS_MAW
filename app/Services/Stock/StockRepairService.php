<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perbaikan data stok yang dijalankan dari CLI (superadmin/VPS).
 *
 * Semua operasi:
 *  - menyentuh SATU bucket saja: (product_id, rack_id) — rack_id null = RELAY;
 *  - atomik (lockForUpdate + transaksi);
 *  - mencatat penyesuaiannya sebagai baris STOCK OPNAME (`FIX-…`, notes = alasan)
 *    sehingga bisa ditelusuri di Riwayat Opname dan tetap konsisten dengan buku
 *    besar `StockLedger`;
 *  - boleh dijalankan tanpa `--apply` (dry-run) lewat command pemanggil.
 */
class StockRepairService
{
    /** Token kolom rak yang berarti "tanpa rak / relay". */
    public const RELAY_TOKENS = ['', '-', '—', 'relay', 'overflow', 'non rak', 'tanpa rak'];

    /**
     * Cari produk dari ID atau part number (persis dulu, baru mirip).
     */
    public function resolveProduct(string $needle): Product
    {
        $needle = trim($needle);

        if ($needle === '') {
            throw new RuntimeException('--product wajib diisi (ID atau part number).');
        }

        if (ctype_digit($needle)) {
            $product = Product::find((int) $needle);
            if ($product) {
                return $product;
            }
        }

        $exact = Product::where('part_number', $needle)->first();
        if ($exact) {
            return $exact;
        }

        $matches = Product::where('part_number', 'like', "%{$needle}%")
            ->orWhere('name', 'like', "%{$needle}%")
            ->limit(5)
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            throw new RuntimeException("Produk \"{$needle}\" tidak ditemukan.");
        }

        throw new RuntimeException(
            "Produk \"{$needle}\" ambigu, cocok dengan: "
            . $matches->map(fn (Product $p) => "#{$p->id} {$p->part_number}")->implode(', ')
        );
    }

    /**
     * Rak dari kode (atau "#id"); null = RELAY/tanpa rak.
     */
    public function resolveRack(?string $value): ?Rack
    {
        $value = trim((string) $value);

        if (in_array(mb_strtolower($value), self::RELAY_TOKENS, true)) {
            return null;
        }

        if (str_starts_with($value, '#')) {
            $rack = Rack::find((int) substr($value, 1));
            if ($rack) {
                return $rack;
            }

            throw new RuntimeException("Rak {$value} tidak ditemukan.");
        }

        $rack = Rack::whereRaw('UPPER(TRIM(code)) = ?', [mb_strtoupper($value)])->first();

        if (! $rack) {
            throw new RuntimeException("Rak \"{$value}\" tidak ditemukan (pakai kode rak atau RELAY).");
        }

        return $rack;
    }

    /**
     * Semua baris stok satu bucket, urut id (baris pertama = penampung utama).
     *
     * @return Collection<int,Stock>
     */
    public function bucketRows(int $productId, ?int $rackId): Collection
    {
        return Stock::query()
            ->with(['product:id,part_number,name', 'rack:id,code'])
            ->where('product_id', $productId)
            ->when($rackId === null, fn ($q) => $q->whereNull('rack_id'), fn ($q) => $q->where('rack_id', $rackId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Grup (produk × rak/RELAY) yang punya lebih dari satu baris stok.
     *
     * @return array<int,array{product_id:int, rack_id:int|null, rows:int, total:int, detail:string}>
     */
    public function duplicateGroups(?int $productId = null): array
    {
        $groups = DB::table('stocks')
            ->selectRaw('product_id, IFNULL(rack_id, 0) AS rack_key, COUNT(*) AS rows_count, COALESCE(SUM(quantity), 0) AS total, GROUP_CONCAT(CONCAT(id, ":", quantity) ORDER BY id SEPARATOR " | ") AS detail')
            ->when($productId !== null, fn ($q) => $q->where('product_id', $productId))
            ->groupBy('product_id', DB::raw('IFNULL(rack_id, 0)'))
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('rows_count')
            ->get();

        return $groups->map(fn ($g) => [
            'product_id' => (int) $g->product_id,
            'rack_id' => ((int) $g->rack_key) === 0 ? null : (int) $g->rack_key,
            'rows' => (int) $g->rows_count,
            'total' => (int) $g->total,
            'detail' => (string) $g->detail,
        ])->all();
    }

    /**
     * Gabungkan baris duplikat satu bucket: qty dijumlahkan ke baris id terkecil,
     * baris sisanya dihapus. Aman karena baris-baris itu memang bucket yang sama
     * (qty total TIDAK berubah).
     *
     * @return array{before:array, after:array, merged_rows:int, total:int}
     */
    public function mergeDuplicates(int $productId, ?int $rackId, string $reason): array
    {
        return DB::transaction(function () use ($productId, $rackId, $reason) {
            $rows = $this->bucketRows($productId, $rackId);

            if ($rows->count() < 2) {
                throw new RuntimeException('Tidak ada baris duplikat untuk bucket ini.');
            }

            $before = $this->snapshot($rows);
            $total = (int) $rows->sum('quantity');
            $removed = $total - (int) $rows->first()->quantity;

            $keeper = $rows->first();
            $keeper->quantity = $total;
            $keeper->save();

            Stock::whereIn('id', $rows->skip(1)->pluck('id'))->delete();

            $after = $this->snapshot($this->bucketRows($productId, $rackId));

            // Qty tidak berubah → catat sebagai penyesuaian 0 supaya tetap terlacak.
            $this->recordOpname($productId, [[
                'rack_id' => $rackId,
                'sap_qty' => $total,
                'actual_qty' => $total,
            ]], $reason . " (gabung {$removed} baris duplikat)");

            return [
                'before' => $before,
                'after' => $after,
                'merged_rows' => $rows->count() - 1,
                'total' => $total,
            ];
        });
    }

    /**
     * Set qty satu bucket ke nilai absolut.
     * qty 0 → baris bucket dihapus (baris kosong bikin bingung di daftar stok).
     *
     * `sap_qty` yang dicatat = nilai menurut BUKU BESAR (bukan nilai tabel stok),
     * supaya setelah perbaikan stok dan buku besar konsisten by construction.
     *
     * @return array{before:array, after:array, before_qty:int, after_qty:int, ledger:int}
     */
    public function setQuantity(int $productId, ?int $rackId, int $target, string $reason): array
    {
        if ($target < 0) {
            throw new RuntimeException('Qty tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $rackId, $target, $reason) {
            $rows = $this->bucketRows($productId, $rackId);
            $before = $this->snapshot($rows);
            $beforeQty = (int) $rows->sum('quantity');
            $ledger = $this->ledgerExpected($productId, $rackId);

            $this->writeBucket($productId, $rackId, $target);

            $after = $this->snapshot($this->bucketRows($productId, $rackId));

            $this->recordOpname($productId, [[
                'rack_id' => $rackId,
                'sap_qty' => $ledger,
                'actual_qty' => $target,
            ]], $reason);

            return [
                'before' => $before,
                'after' => $after,
                'before_qty' => $beforeQty,
                'after_qty' => $target,
                'ledger' => $ledger,
            ];
        });
    }

    /**
     * Pindahkan qty dari satu bucket ke bucket lain (mis. RELAY → rak asli).
     * `$qty` null = pindahkan seluruhnya.
     *
     * @return array{before:array, after:array, moved:int, source_after:int, target_after:int}
     */
    public function move(int $productId, ?int $sourceRackId, ?int $targetRackId, ?int $qty, string $reason): array
    {
        if ($sourceRackId === $targetRackId) {
            throw new RuntimeException('Rak asal dan tujuan sama.');
        }

        return DB::transaction(function () use ($productId, $sourceRackId, $targetRackId, $qty, $reason) {
            $sourceRows = $this->bucketRows($productId, $sourceRackId);
            $targetRows = $this->bucketRows($productId, $targetRackId);

            $sourceQty = (int) $sourceRows->sum('quantity');
            $targetQty = (int) $targetRows->sum('quantity');
            $moveQty = $qty ?? $sourceQty;

            if ($moveQty <= 0) {
                throw new RuntimeException('Tidak ada qty untuk dipindah di bucket asal.');
            }

            if ($moveQty > $sourceQty) {
                throw new RuntimeException("Qty pindah ({$moveQty}) melebihi isi bucket asal ({$sourceQty}).");
            }

            $before = array_merge($this->snapshot($sourceRows), $this->snapshot($targetRows));
            $ledgerSource = $this->ledgerExpected($productId, $sourceRackId);
            $ledgerTarget = $this->ledgerExpected($productId, $targetRackId);

            $this->writeBucket($productId, $sourceRackId, $sourceQty - $moveQty);
            $this->writeBucket($productId, $targetRackId, $targetQty + $moveQty);

            $after = array_merge(
                $this->snapshot($this->bucketRows($productId, $sourceRackId)),
                $this->snapshot($this->bucketRows($productId, $targetRackId))
            );

            $this->recordOpname($productId, [
                ['rack_id' => $sourceRackId, 'sap_qty' => $ledgerSource, 'actual_qty' => $sourceQty - $moveQty],
                ['rack_id' => $targetRackId, 'sap_qty' => $ledgerTarget, 'actual_qty' => $targetQty + $moveQty],
            ], $reason . " (pindah {$moveQty} pcs)");

            return [
                'before' => $before,
                'after' => $after,
                'moved' => $moveQty,
                'source_after' => $sourceQty - $moveQty,
                'target_after' => $targetQty + $moveQty,
            ];
        });
    }

    /** Nilai bucket menurut buku besar transaksi. */
    public function ledgerExpected(int $productId, ?int $rackId): int
    {
        return (int) ((new StockLedger())->expected()[StockLedger::key($productId, $rackId)] ?? 0);
    }

    /**
     * Tulis isi bucket: qty<=0 → hapus barisnya; kalau ada baris duplikat,
     * semuanya disatukan ke baris id terkecil.
     */
    private function writeBucket(int $productId, ?int $rackId, int $qty): void
    {
        $rows = $this->bucketRows($productId, $rackId);

        if ($qty <= 0) {
            Stock::whereIn('id', $rows->pluck('id'))->delete();

            return;
        }

        if ($rows->isEmpty()) {
            Stock::create([
                'product_id' => $productId,
                'rack_id' => $rackId,
                'quantity' => $qty,
            ]);

            return;
        }

        $keeper = $rows->first();
        $keeper->quantity = $qty;
        $keeper->save();

        if ($rows->count() > 1) {
            Stock::whereIn('id', $rows->skip(1)->pluck('id'))->delete();
        }
    }

    /**
     * @param  Collection<int,Stock>  $rows
     * @return array<int,array{product_id:int, part_number:string, product_name:string, rack_id:int|null, rack_code:string, quantity:int}>
     */
    public function snapshot(Collection $rows): array
    {
        return $rows->map(fn (Stock $s) => [
            'product_id' => (int) $s->product_id,
            'part_number' => $s->product?->part_number ?? '#' . $s->product_id,
            'product_name' => $s->product?->name ?? '',
            'rack_id' => $s->rack_id === null ? null : (int) $s->rack_id,
            'rack_code' => $s->rack?->code ?? 'RELAY',
            'quantity' => (int) $s->quantity,
        ])->values()->all();
    }

    /**
     * Catat perbaikan sebagai baris STOCK OPNAME (bukan stock_corrections).
     *
     * Alasannya: opname adalah penyesuaian absolut yang sudah dipahami buku besar
     * (`StockLedger::opnameAdjustments()`), jadi setelah perbaikan stok dan buku
     * besar tetap konsisten. Sekaligus perbaikan ini muncul di Riwayat Opname
     * aplikasi (beserta alasannya di kolom notes) sehingga bisa diaudit user.
     *
     * `sap_qty` diisi nilai BUKU BESAR (bukan nilai tabel stok) supaya hasil
     * akhirnya konsisten walau stok sebelumnya sudah drift.
     *
     * @param  array<int,array{rack_id:int|null, sap_qty:int, actual_qty:int}>  $buckets
     */
    private function recordOpname(int $productId, array $buckets, string $reason): void
    {
        $product = Product::find($productId);

        if (! $product || empty($buckets)) {
            return;
        }

        $today = now()->toDateString();
        $seq = StockOpname::whereDate('opname_date', $today)->count() + 1;
        $diffs = array_filter($buckets, fn ($b) => (int) $b['sap_qty'] !== (int) $b['actual_qty']);

        $opname = StockOpname::create([
            'code' => 'FIX-' . str_replace('-', '', $today) . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'opname_date' => $today,
            'total_items' => count($buckets),
            'diff_items' => count($diffs),
            'new_items' => 0,
            'created_by' => auth()->id(),
            'notes' => '[Perbaikan CLI] ' . $reason,
        ]);

        foreach ($buckets as $bucket) {
            StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'product_id' => $productId,
                'rack_id' => $bucket['rack_id'],
                'part_number' => (string) $product->part_number,
                'product_name' => (string) $product->name,
                'rack_code' => $bucket['rack_id'] === null ? null : Rack::whereKey($bucket['rack_id'])->value('code'),
                'sap_qty' => (int) $bucket['sap_qty'],
                'actual_qty' => (int) $bucket['actual_qty'],
                'diff' => (int) $bucket['actual_qty'] - (int) $bucket['sap_qty'],
            ]);
        }
    }
}
