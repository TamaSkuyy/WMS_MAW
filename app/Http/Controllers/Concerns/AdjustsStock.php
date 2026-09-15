<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Stock;

/**
 * Penyesuaian stok atomik untuk aksi massal (hapus transaksi).
 *
 * `quantity` di tabel stocks bertipe UNSIGNED, jadi pengurangan tidak boleh
 * menghasilkan negatif: kelebihannya dicatat sebagai "shortage" (stok sudah
 * habis/tidak cukup) dan nilai stok dijepit di 0.
 */
trait AdjustsStock
{
    /**
     * @param  int  $delta  Positif = tambah stok (mis. batalkan pengiriman),
     *                      negatif = kurangi stok (mis. batalkan penerimaan).
     * @return array{applied:int, shortage:int}
     */
    protected function adjustStock(int $productId, ?int $rackId, int $delta): array
    {
        $stock = Stock::query()
            ->where('product_id', $productId)
            ->when($rackId === null, fn ($q) => $q->whereNull('rack_id'), fn ($q) => $q->where('rack_id', $rackId))
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            if ($delta <= 0) {
                return ['applied' => 0, 'shortage' => -$delta];
            }

            $stock = Stock::create([
                'product_id' => $productId,
                'rack_id' => $rackId,
                'quantity' => 0,
            ]);
        }

        $before = (int) $stock->quantity;
        $after = $before + $delta;
        $shortage = 0;

        if ($after < 0) {
            $shortage = -$after;
            $after = 0;
        }

        $stock->quantity = $after;
        $stock->save();

        return ['applied' => $after - $before, 'shortage' => $shortage];
    }
}
