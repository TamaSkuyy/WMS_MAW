<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Rack;
use App\Services\Stock\StockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit stok (READ-ONLY) — menjawab "kok stoknya dobel?" dengan bukti:
 *
 *  1. baris stok duplikat (produk + rak/RELAY sama muncul lebih dari sekali)
 *  2. kandidat dobel RELAY (produk punya baris rak DAN baris RELAY)
 *  3. baris RELAY yang lahir dari stock opname (sap 0 → dibuat baru)
 *  4. selisih stok sistem vs buku besar transaksi (terima/kirim/opname/koreksi)
 *
 * Tidak mengubah data sama sekali. Perbaikan ada di `stocks:merge-duplicates`
 * dan `stocks:set-quantity`.
 */
class StockAudit extends Command
{
    protected $signature = 'stocks:audit
                            {--search= : Filter part number / nama produk}
                            {--limit=30 : Maksimal baris yang ditampilkan per bagian}
                            {--no-ledger : Lewati perhitungan buku besar (lebih cepat pada DB besar)}
                            {--json : Keluarkan JSON (untuk dilaporkan/diolah)}';

    protected $description = 'Audit stok (read-only): duplikat baris, relay dobel, dan selisih vs buku besar transaksi';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $search = trim((string) $this->option('search'));

        $products = $this->productLookup($search);
        $racks = Rack::pluck('code', 'id');

        $summary = $this->summary();
        $duplicates = $this->duplicateRows($products, $limit);
        $relayDoubles = $this->relayDoubles($products, $limit);
        $opnameRelay = $this->relayCreatedByOpname($products, $limit);
        $ledgerDiffs = $this->option('no-ledger')
            ? null
            : $this->ledgerDiffs($products, $limit);

        if ($this->option('json')) {
            $this->line(json_encode(compact('summary', 'duplicates', 'relayDoubles', 'opnameRelay', 'ledgerDiffs'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->render($summary, $duplicates, $relayDoubles, $opnameRelay, $ledgerDiffs, $racks, $limit, $search);

        return self::SUCCESS;
    }

    /** @return array<string,int> */
    private function summary(): array
    {
        $row = DB::table('stocks')
            ->selectRaw('COUNT(*) AS rows_total, COALESCE(SUM(quantity), 0) AS qty_total')
            ->first();

        return [
            'rows_total' => (int) $row->rows_total,
            'qty_total' => (int) $row->qty_total,
            'rows_relay' => (int) DB::table('stocks')->whereNull('rack_id')->count(),
            'rows_relay_qty' => (int) DB::table('stocks')->whereNull('rack_id')->sum('quantity'),
            'rows_rack' => (int) DB::table('stocks')->whereNotNull('rack_id')->count(),
            'products_relay' => (int) DB::table('stocks')->whereNull('rack_id')->distinct()->count('product_id'),
            'products_mixed' => (int) DB::table('stocks')
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('SUM(rack_id IS NULL) > 0 AND SUM(rack_id IS NOT NULL) > 0')
                ->get()
                ->count(),
        ];
    }

    /**
     * Baris dengan kunci (produk + rak/RELAY) yang sama muncul lebih dari sekali.
     *
     * @param  array<int,Product>  $products
     */
    private function duplicateRows(array $products, int $limit): array
    {
        $rows = DB::table('stocks')
            ->selectRaw('product_id, IFNULL(rack_id, 0) AS rack_key, COUNT(*) AS n, GROUP_CONCAT(CONCAT(id, ":", quantity) ORDER BY id SEPARATOR " | ") AS detail, COALESCE(SUM(quantity), 0) AS total')
            ->groupBy('product_id', DB::raw('IFNULL(rack_id, 0)'))
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('n')
            ->get();

        return $rows
            ->filter(fn ($r) => $this->matches($products, (int) $r->product_id))
            ->take($limit)
            ->map(fn ($r) => [
                'product_id' => (int) $r->product_id,
                'part_number' => $products[(int) $r->product_id]->part_number ?? '#' . $r->product_id,
                'product_name' => $products[(int) $r->product_id]->name ?? '',
                'rack' => ((int) $r->rack_key) === 0 ? 'RELAY' : '#' . $r->rack_key,
                'rows' => (int) $r->n,
                'detail' => $r->detail,
                'total' => (int) $r->total,
            ])
            ->values()
            ->all();
    }

    /**
     * Produk yang punya baris RELAY berisi qty DAN baris rak — kandidat "dobel".
     *
     * @param  array<int,Product>  $products
     */
    private function relayDoubles(array $products, int $limit): array
    {
        $relayQty = DB::table('stocks')->whereNull('rack_id')->pluck('quantity', 'product_id');
        $rackRows = DB::table('stocks as s')
            ->join('racks as r', 'r.id', '=', 's.rack_id')
            ->whereNotNull('s.rack_id')
            ->get(['s.product_id', 's.quantity', 'r.code'])
            ->groupBy('product_id');

        $out = [];

        foreach ($relayQty as $productId => $relay) {
            $productId = (int) $productId;
            $relay = (int) $relay;

            if ($relay <= 0 || ! isset($rackRows[$productId]) || ! $this->matches($products, $productId)) {
                continue;
            }

            $racks = $rackRows[$productId];
            $rackTotal = (int) $racks->sum('quantity');

            $out[] = [
                'product_id' => $productId,
                'part_number' => $products[$productId]->part_number ?? '#' . $productId,
                'product_name' => $products[$productId]->name ?? '',
                'relay' => $relay,
                'rack_total' => $rackTotal,
                'rack_detail' => $racks->map(fn ($r) => $r->code . ':' . (int) $r->quantity)->implode(' | '),
                'indication' => $relay === $rackTotal
                    ? 'KUAT — qty relay = total rak'
                    : ($rackTotal > $relay ? 'sedang — relay sebagian dari rak' : 'lemah'),
                'score' => $relay === $rackTotal ? 0 : 1,
            ];
        }

        usort($out, fn ($a, $b) => [$a['score'], -$a['relay']] <=> [$b['score'], -$b['relay']]);

        return array_slice($out, 0, $limit);
    }

    /**
     * Baris stock opname yang menandakan baris stok RELAY baru dibuat
     * (qty sistem 0 → dibuat baru). Ini sumber klasik "RELAY dobel".
     *
     * @param  array<int,Product>  $products
     */
    private function relayCreatedByOpname(array $products, int $limit): array
    {
        return DB::table('stock_opname_items as oi')
            ->join('stock_opnames as o', 'o.id', '=', 'oi.stock_opname_id')
            ->whereNull('oi.rack_code')
            ->where('oi.sap_qty', 0)
            ->where('oi.diff', '<>', 0)
            ->orderByDesc('oi.diff')
            ->get(['o.code', 'o.opname_date', 'oi.product_id', 'oi.part_number', 'oi.product_name', 'oi.actual_qty', 'oi.diff'])
            ->filter(fn ($r) => $this->matches($products, (int) $r->product_id))
            ->take($limit)
            ->map(fn ($r) => [
                'opname' => $r->code,
                'date' => (string) $r->opname_date,
                'product_id' => (int) $r->product_id,
                'part_number' => $r->part_number,
                'product_name' => $r->product_name,
                'qty_dibuat' => (int) $r->actual_qty,
            ])
            ->values()
            ->all();
    }

    /**
     * Selisih stok sistem vs buku besar transaksi (produk × rak/RELAY).
     *
     * @param  array<int,Product>  $products
     */
    private function ledgerDiffs(array $products, int $limit): array
    {
        $ledger = new StockLedger();

        return collect($ledger->diffs())
            ->filter(fn ($row) => $this->matches($products, $row['product_id']))
            ->map(fn ($row) => array_merge($row, [
                'part_number' => $products[$row['product_id']]->part_number ?? '#' . $row['product_id'],
                'product_name' => $products[$row['product_id']]->name ?? '',
            ]))
            ->sortByDesc(fn ($row) => abs($row['diff']))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<int,Product>  $products
     */
    private function matches(array $products, int $productId): bool
    {
        return isset($products[$productId]);
    }

    /**
     * @return array<int,Product>
     */
    private function productLookup(string $search): array
    {
        return Product::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('part_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->get(['id', 'part_number', 'name'])
            ->keyBy('id')
            ->all();
    }

    private function render(array $summary, array $duplicates, array $relayDoubles, array $opnameRelay, ?array $ledgerDiffs, $racks, int $limit, string $search): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║  AUDIT STOK — WMS MAW (read-only, tidak mengubah data)       ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        if ($search !== '') {
            $this->line("  filter: \"{$search}\"");
        }

        $this->newLine();
        $this->line('<options=bold>RINGKASAN</>');
        $this->line(sprintf('  baris stok            : %d (total qty %s)', $summary['rows_total'], number_format($summary['qty_total'], 0, ',', '.')));
        $this->line(sprintf('  • di rak              : %d', $summary['rows_rack']));
        $this->line(sprintf('  • RELAY (tanpa rak)   : %d (qty %s)', $summary['rows_relay'], number_format($summary['rows_relay_qty'], 0, ',', '.')));
        $this->line(sprintf('  produk punya RELAY    : %d', $summary['products_relay']));
        $this->line(sprintf('  produk RELAY + rak    : %d', $summary['products_mixed']));

        $this->newLine();
        $this->line('<options=bold>1) BARIS DUPLIKAT (produk + rak/RELAY sama muncul >1×)</>');
        if (empty($duplicates)) {
            $this->line('   <fg=green>tidak ada ✓</>');
        } else {
            $this->table(
                ['part_number', 'produk', 'rak', 'baris', 'id:qty', 'total'],
                array_map(fn ($r) => [$r['part_number'], mb_substr($r['product_name'], 0, 30), $r['rack'], $r['rows'], $r['detail'], $r['total']], $duplicates)
            );
            $this->line('   → perbaiki dengan: <fg=yellow>php artisan stocks:merge-duplicates --apply</>');
        }

        $this->newLine();
        $this->line('<options=bold>2) KANDIDAT DOBEL RELAY (produk punya qty di rak DAN di RELAY)</>');
        if (empty($relayDoubles)) {
            $this->line('   <fg=green>tidak ada ✓</>');
        } else {
            $this->table(
                ['part_number', 'produk', 'rak', 'relay', 'total rak', 'indikasi'],
                array_map(fn ($r) => [$r['part_number'], mb_substr($r['product_name'], 0, 30), $r['rack_detail'], $r['relay'], $r['rack_total'], $r['indication']], $relayDoubles)
            );
            $this->line('   → kalau RELAY memang barang fisik yang sama dengan rak: <fg=yellow>php artisan stocks:set-quantity --product=ID --rack=RELAY --qty=0 --reason="..." --apply</>');
            $this->line('     kalau RELAY hanya salah rak: <fg=yellow>--move-to=KODE_RAK</>');
        }

        $this->newLine();
        $this->line('<options=bold>3) BARIS RELAY YANG LAHIR DARI STOCK OPNAME (qty sistem 0 → dibuat baru)</>');
        if (empty($opnameRelay)) {
            $this->line('   <fg=green>tidak ada ✓</>');
        } else {
            $this->table(
                ['opname', 'tanggal', 'part_number', 'produk', 'qty dibuat'],
                array_map(fn ($r) => [$r['opname'], $r['date'], $r['part_number'], mb_substr($r['product_name'], 0, 30), $r['qty_dibuat']], $opnameRelay)
            );
        }

        $this->newLine();
        $this->line('<options=bold>4) SELISIH STOK SISTEM vs BUKU BESAR TRANSAKSI</>');
        if ($ledgerDiffs === null) {
            $this->line('   (dilewati — opsi --no-ledger)');
        } elseif (empty($ledgerDiffs)) {
            $this->line('   <fg=green>stok sistem cocok dengan riwayat transaksi ✓</>');
        } else {
            $this->table(
                ['part_number', 'produk', 'rak', 'sistem', 'seharusnya', 'selisih'],
                array_map(fn ($r) => [
                    $r['part_number'],
                    mb_substr($r['product_name'], 0, 30),
                    $r['rack_id'] === null ? 'RELAY' : ($racks[$r['rack_id']] ?? '#' . $r['rack_id']),
                    $r['actual'],
                    $r['expected'],
                    $r['diff'],
                ], $ledgerDiffs)
            );
            $this->line(sprintf('   ditampilkan maksimal %d baris selisih terbesar. Pakai --limit=N untuk melihat lebih banyak.', $limit));
            $this->line('   → samakan satu per satu: <fg=yellow>php artisan stocks:set-quantity --product=ID --rack=RAK --qty=N --reason="..." --apply</>');
            $this->line('     atau ikut buku besar: tambahkan <fg=yellow>--from-ledger</>.');
            $this->line('   Catatan: kalau pernah memakai Pemutihan Data mode "--keep-stock", buku besar tidak bisa dipakai (riwayat dihapus, stok disimpan).');
        }

        $this->newLine();
    }
}
