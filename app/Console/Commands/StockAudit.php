<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Rack;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockRepairService;
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
        $relay = $this->relayAnalysis($products, $limit);
        $opnameRelay = $this->relayCreatedByOpname($products, $limit);
        $opnameHistory = $this->opnameHistory();
        $ledgerDiffs = $this->option('no-ledger')
            ? null
            : $this->ledgerDiffs($products, $limit);

        if ($this->option('json')) {
            $this->line(json_encode(compact('summary', 'duplicates', 'relay', 'opnameRelay', 'opnameHistory', 'ledgerDiffs'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->render($summary, $duplicates, $relay, $opnameRelay, $opnameHistory, $ledgerDiffs, $racks, $limit, $search);

        return self::SUCCESS;
    }

    /**
     * Ringkasan 10 opname terakhir: berapa baris isinya RELAY vs rak.
     * Kalau `relay_items` = seluruh baris, file opname memang tanpa kolom RAK —
     * persis penyebab baris RELAY palsu.
     *
     * @return array<int,array>
     */
    private function opnameHistory(): array
    {
        return DB::table('stock_opnames as o')
            ->leftJoin('stock_opname_items as oi', 'oi.stock_opname_id', '=', 'o.id')
            ->groupBy('o.id', 'o.code', 'o.opname_date', 'o.total_items', 'o.diff_items', 'o.new_items', 'o.notes')
            ->orderByDesc('o.id')
            ->limit(10)
            ->get([
                'o.code',
                'o.opname_date',
                'o.total_items',
                'o.diff_items',
                'o.new_items',
                'o.notes',
                DB::raw('SUM(oi.rack_code IS NULL) AS relay_items'),
                DB::raw('SUM(oi.rack_code IS NOT NULL) AS rack_items'),
            ])
            ->map(fn ($r) => [
                'code' => $r->code,
                'date' => (string) $r->opname_date,
                'total_items' => (int) $r->total_items,
                'new_items' => (int) $r->new_items,
                'relay_items' => (int) $r->relay_items,
                'rack_items' => (int) $r->rack_items,
                'notes' => (string) ($r->notes ?? ''),
            ])
            ->all();
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
     * Analisis baris RELAY per produk (PALSU / SAH / PERIKSA / KOSONG).
     *
     * @param  array<int,Product>  $products
     * @return array{rows:array<int,array>, summary:array<string,array{count:int, qty:int}>}
     */
    private function relayAnalysis(array $products, int $limit): array
    {
        $all = (new StockRepairService())->relayBuckets();
        $filtered = array_values(array_filter($all, fn ($r) => $this->matches($products, $r['product_id'])));

        $summary = [];

        foreach ($filtered as $row) {
            $v = $row['verdict'];
            $summary[$v] ??= ['count' => 0, 'qty' => 0];
            $summary[$v]['count']++;
            $summary[$v]['qty'] += $row['relay'];
        }

        return ['rows' => array_slice($filtered, 0, $limit), 'summary' => $summary];
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

    private function render(array $summary, array $duplicates, array $relay, array $opnameRelay, array $opnameHistory, ?array $ledgerDiffs, $racks, int $limit, string $search): void
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
        $this->line('<options=bold>2) BARIS RELAY — PALSU (dobel) atau SAH (relay/overflow asli)?</>');
        if (empty($relay['rows'])) {
            $this->line('   <fg=green>tidak ada baris RELAY berisi qty ✓</>');
        } else {
            $this->table(
                ['part_number', 'produk', 'rak', 'relay', 'total rak', 'terima ke RELAY', 'dibuat opname', 'verdict'],
                array_map(fn ($r) => [
                    $r['part_number'],
                    mb_substr($r['product_name'], 0, 26),
                    mb_substr($r['rack_detail'], 0, 26),
                    $r['relay'],
                    $r['rack_total'],
                    $r['inbound_relay'],
                    $r['opname'] ?? '-',
                    $r['verdict'],
                ], $relay['rows'])
            );

            $s = $relay['summary'];
            $this->line(sprintf(
                '   PALSU: %d produk / %s pcs  •  PERIKSA: %d / %s  •  SAH: %d / %s  •  KOSONG(0 pcs): %d',
                $s['PALSU']['count'] ?? 0, number_format($s['PALSU']['qty'] ?? 0, 0, ',', '.'),
                $s['PERIKSA']['count'] ?? 0, number_format($s['PERIKSA']['qty'] ?? 0, 0, ',', '.'),
                $s['SAH']['count'] ?? 0, number_format($s['SAH']['qty'] ?? 0, 0, ',', '.'),
                $s['KOSONG']['count'] ?? 0,
            ));
            $this->line('   Arti: PALSU = tidak pernah ada penerimaan ke RELAY tapi barisnya berisi & dibuat opname → dobel (barangnya sudah di rak).');
            $this->line('         SAH  = memang pernah diterima tanpa rak (jangan diubah!). PERIKSA = perlu dilihat manusia.');
            $this->line(sprintf('   → tampil maksimal %d baris. Semua kandidat PALSU dinolkan sekaligus dengan:', $limit));
            $this->line('     <fg=yellow>php artisan stocks:fix-phantom-relay --apply --reason="hapus baris RELAY palsu hasil opname"</>');
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
        $this->line('<options=bold>3b) RIWAYAT OPNAME TERAKHIR (relay_items = baris tanpa RAK)</>');
        if (empty($opnameHistory)) {
            $this->line('   belum ada riwayat opname');
        } else {
            $this->table(
                ['opname', 'tanggal', 'baris', 'baru', 'relay_items', 'rak_items', 'notes'],
                array_map(fn ($o) => [
                    $o['code'],
                    $o['date'],
                    $o['total_items'],
                    $o['new_items'],
                    $o['relay_items'],
                    $o['rack_items'],
                    mb_substr($o['notes'], 0, 30),
                ], $opnameHistory)
            );
            $this->line('   Kalau relay_items = seluruh baris → file opname itu memang tanpa kolom RAK (biang baris RELAY palsu).');
        }

        $this->newLine();
        $this->line('<options=bold>4) SELISIH STOK SISTEM vs BUKU BESAR TRANSAKSI</>');
        if ($ledgerDiffs === null) {
            $this->line('   (dilewati — opsi --no-ledger)');
        } elseif (empty($ledgerDiffs)) {
            $this->line('   <fg=green>stok sistem cocok dengan riwayat transaksi ✓</>');
            $this->line('   Catatan: buku besar MENGHITUNG stock opname sebagai kebenaran, jadi baris RELAY palsu');
            $this->line('   hasil opname tetap "cocok" di sini — nilainya pakai bagian 2 & 3.');
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
