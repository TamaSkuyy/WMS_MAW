<?php

namespace App\Services\Stock;

use App\Models\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Buku besar stok — menghitung berapa seharusnya isi stok dari riwayat
 * transaksi (bukan dari tabel `stocks`), lalu membandingkannya dengan isi
 * tabel stok saat ini.
 *
 * Dipakai untuk menjawab pertanyaan "kok stoknya dobel?" dengan bukti angka:
 * setiap selisih (per produk × rak/RELAY) bisa ditelusuri ke satu sumber.
 *
 * Rumus harian:
 *   seharusnya = Σ qty diterima (cycle_items.received_quantity, kondisi sekarang)
 *              − Σ qty dikirim (shopping_items berstatus shipped/cripple/completed)
 *              + Σ selisih stock opname (termasuk perbaikan CLI `stocks:set-quantity`,
 *                yang mencatat penyesuaiannya sebagai baris stock opname)
 *
 * Kenapa pakai keadaan SEKARANG, bukan riwayat receive_logs?
 * - `receive_logs` baru ada sejak 2026-07-29, jadi penerimaan lama tidak punya
 *   log (buku besar jadi minus besar).
 * - Koreksi cycle/shopping SUDAH memperbarui `cycle_items`/`shopping_items`,
 *   jadi dampaknya otomatis ikut terhitung — tidak boleh dihitung dua kali.
 * - Penghapusan transaksi massal (bulk delete) juga konsisten: barisnya hilang
 *   dari buku besar bersamaan dengan koreksi stoknya.
 *
 * "Pemutihan Data" (DataResetService) yang mempertahankan stok
 * (`--keep-stock`) menghapus riwayat tanpa mengubah stok, sehingga buku besar
 * TIDAK bisa dipakai setelahnya.
 */
class StockLedger
{
    /** Status shopping yang sudah mengurangi stok. */
    public const OUT_STATUSES = ['shipped', 'cripple', 'completed'];

    /** Kunci bucket stok: "productId|rackId" (rack null → "null" = RELAY). */
    public static function key(?int $productId, ?int $rackId): string
    {
        return $productId . '|' . ($rackId ?? 'null');
    }

    /** @return array{product_id:int, rack_id:int|null} */
    public static function parseKey(string $key): array
    {
        [$productId, $rackId] = explode('|', $key, 2);

        return [
            'product_id' => (int) $productId,
            'rack_id' => ($rackId === 'null' || $rackId === '') ? null : (int) $rackId,
        ];
    }

    /**
     * Barang masuk per bucket — dari `cycle_items.received_quantity` kondisi
     * sekarang (sudah termasuk koreksi cycle, tanpa bergantung receive_logs
     * yang baru ada sejak 2026-07-29).
     *
     * @return array<string,int>
     */
    public function inbound(): array
    {
        return $this->sumQuery(
            DB::table('cycle_items')
                ->groupBy('product_id', 'rack_id')
                ->selectRaw('product_id, rack_id, COALESCE(SUM(received_quantity), 0) AS total')
                ->get()
        );
    }

    /**
     * Barang keluar per bucket — dari shopping yang sudah dikirim.
     *
     * @return array<string,int>
     */
    public function outbound(): array
    {
        return $this->sumQuery(
            DB::table('shopping_items as si')
                ->join('shoppings as s', 's.id', '=', 'si.shopping_id')
                ->whereIn('s.status', self::OUT_STATUSES)
                ->groupBy('si.product_id', 'si.rack_id')
                ->selectRaw('si.product_id, si.rack_id, COALESCE(SUM(si.quantity), 0) AS total')
                ->get()
        );
    }

    /**
     * Penyesuaian dari stock opname (diff = qty fisik − qty sistem saat opname).
     *
     * @return array<string,int>
     */
    public function opnameAdjustments(): array
    {
        return $this->sumQuery(
            DB::table('stock_opname_items')
                ->groupBy('product_id', 'rack_id')
                ->selectRaw('product_id, rack_id, COALESCE(SUM(diff), 0) AS total')
                ->get()
        );
    }

    /**
     * Isi stok yang seharusnya menurut riwayat transaksi.
     *
     * @return array<string,int>
     */
    public function expected(): array
    {
        $ledger = $this->inbound();

        foreach ($this->outbound() as $key => $qty) {
            $ledger[$key] = ($ledger[$key] ?? 0) - $qty;
        }

        foreach ($this->opnameAdjustments() as $key => $qty) {
            $ledger[$key] = ($ledger[$key] ?? 0) + $qty;
        }

        return $ledger;
    }

    /**
     * Isi tabel `stocks` saat ini (dijumlahkan per bucket supaya baris duplikat
     * tetap terhitung apa adanya).
     *
     * @return array<string,int>
     */
    public function actual(): array
    {
        return $this->sumQuery(
            Stock::query()
                ->groupBy('product_id', 'rack_id')
                ->selectRaw('product_id, rack_id, COALESCE(SUM(quantity), 0) AS total')
                ->get()
        );
    }

    /**
     * Selisih stok sistem vs buku besar, hanya yang tidak nol.
     *
     * @return array<string,array{product_id:int, rack_id:int|null, expected:int, actual:int, diff:int}>
     */
    public function diffs(): array
    {
        $expected = $this->expected();
        $actual = $this->actual();
        $out = [];

        foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $key) {
            $want = (int) ($expected[$key] ?? 0);
            $have = (int) ($actual[$key] ?? 0);

            if ($want === $have) {
                continue;
            }

            $out[$key] = array_merge(self::parseKey($key), [
                'expected' => $want,
                'actual' => $have,
                'diff' => $have - $want,
            ]);
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,object>  $rows
     * @return array<string,int>
     */
    private function sumQuery($rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[self::key((int) $row->product_id, $row->rack_id === null ? null : (int) $row->rack_id)]
                = (int) $row->total;
        }

        return $out;
    }
}
