<?php

namespace App\Services\Shopping;

use App\Models\Rack;
use App\Models\Shopping;
use Illuminate\Support\Facades\DB;

/**
 * Perencana "Kirim Massal" — mensimulasikan pengiriman SEMUA shopping draft
 * sekaligus SEBELUM stok benar-benar dikurangi.
 *
 * Dipakai dua tempat supaya hasilnya konsisten:
 *  - `ShoppingController::bulkShipPreview()` → leader melihat dulu: berapa frame
 *    siap, berapa yang stoknya kurang (dan kurang berapa), total qty.
 *  - `ShoppingController::bulkShip()` → mengeksekusi daftar yang sudah dihitung,
 *    jadi satu klik langsung mengirim semua tanpa pilih satu-satu.
 *
 * Kenapa harus disimulasikan, bukan dicek per shopping?
 * Stok itu dipakai bersama antar frame. Stok 100 pcs dengan dua frame masing-masing
 * butuh 60 pcs: kalau dicek satu-satu keduanya "cukup", padahal totalnya tidak.
 * Simulasi mengurangi stok berjalan secara berurutan (urut id = urutan import),
 * jadi frame yang kehabisan jatah stok ditandai TIDAK siap sejak awal.
 */
class BulkShipPlanner
{
    /**
     * Batas jumlah shopping yang diproses dalam satu permintaan (sisanya
     * dilaporkan sebagai `remaining` supaya bisa dikirim lagi).
     */
    public const MAX_APPLY = 500;

    /**
     * @param  array<int,int>|null  $ids  null = semua draft
     * @return array{
     *     total:int, ready:array<int,array>, blocked:array<int,array>,
     *     summary:array{total:int, ready:int, blocked:int, items:int, quantity:int, blocked_quantity:int}
     * }
     */
    public function plan(?array $ids = null, bool $all = false): array
    {
        $rackCodes = Rack::pluck('code', 'id');
        $stock = $this->stockByBucket();

        $ready = [];
        $blocked = [];
        $totalItems = 0;
        $totalQty = 0;
        $blockedQty = 0;

        $query = Shopping::query()
            ->where('status', 'draft')
            ->when(! $all && $ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->with([
                'items:id,shopping_id,product_id,rack_id,quantity',
                'items.product:id,part_number,name',
                'shoppingLocation:id,name',
            ]);

        $query->chunkById(100, function ($shoppings) use (&$ready, &$blocked, &$totalItems, &$totalQty, &$blockedQty, $stock, $rackCodes) {
            foreach ($shoppings as $shopping) {
                $needs = [];

                foreach ($shopping->items as $item) {
                    $key = $this->key((int) $item->product_id, $item->rack_id === null ? null : (int) $item->rack_id);
                    $needs[$key] = ($needs[$key] ?? 0) + (int) $item->quantity;
                }

                $itemCount = $shopping->items->count();
                $qty = (int) $shopping->items->sum('quantity');
                $totalItems += $itemCount;
                $totalQty += $qty;

                $base = [
                    'id' => (int) $shopping->id,
                    'frame_number' => (string) ($shopping->frame_number ?? '(tanpa frame)'),
                    'location' => $shopping->shoppingLocation?->name,
                    'is_cripple' => (bool) $shopping->is_cripple,
                    'items' => $itemCount,
                    'quantity' => $qty,
                ];

                if (empty($needs)) {
                    $blocked[] = $base + ['issues' => ['Belum ada barang — import barang untuk frame ini dulu.']];
                    $blockedQty += $qty;

                    continue;
                }

                $issues = [];

                foreach ($needs as $key => $need) {
                    $available = (int) ($stock[$key] ?? 0);

                    if ($available < $need) {
                        $issues[] = $this->shortageMessage($shopping, $key, $need, $available, $rackCodes);
                    }
                }

                if (! empty($issues)) {
                    $blocked[] = $base + ['issues' => array_slice($issues, 0, 5)];
                    $blockedQty += $qty;

                    continue;
                }

                // Siap kirim → kurangi stok berjalan supaya frame berikutnya
                // dinilai dari sisa stok yang sebenarnya.
                foreach ($needs as $key => $need) {
                    $stock[$key] = (int) ($stock[$key] ?? 0) - $need;
                }

                $ready[] = $base;
            }
        });

        return [
            'total' => count($ready) + count($blocked),
            'ready' => $ready,
            'blocked' => $blocked,
            'summary' => [
                'total' => count($ready) + count($blocked),
                'ready' => count($ready),
                'blocked' => count($blocked),
                'items' => $totalItems,
                'quantity' => $totalQty,
                'blocked_quantity' => $blockedQty,
            ],
        ];
    }

    /** @return array<string,int> */
    private function stockByBucket(): array
    {
        $rows = DB::table('stocks')
            ->selectRaw('product_id, rack_id, COALESCE(SUM(quantity), 0) AS total')
            ->groupBy('product_id', 'rack_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$this->key((int) $row->product_id, $row->rack_id === null ? null : (int) $row->rack_id)] = (int) $row->total;
        }

        return $out;
    }

    private function key(int $productId, ?int $rackId): string
    {
        return $productId . '|' . ($rackId ?? 'null');
    }

    /** Pesan kekurangan stok yang menyebut part number & raknya. */
    private function shortageMessage(Shopping $shopping, string $key, int $need, int $available, $rackCodes): string
    {
        [$productId, $rackId] = explode('|', $key, 2);
        $item = $shopping->items->firstWhere('product_id', (int) $productId);
        $part = $item?->product?->part_number ?? ('#' . $productId);
        $rack = ($rackId === 'null') ? 'RELAY' : ($rackCodes[(int) $rackId] ?? ('#' . $rackId));
        $short = $need - $available;

        return "Stok kurang: {$part} di rak {$rack} — butuh {$need}, tersedia {$available} (kurang {$short})";
    }
}
