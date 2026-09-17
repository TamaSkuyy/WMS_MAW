<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cegah baris stok duplikat untuk produk yang sama di RELAY (rack_id NULL).
 *
 * Unique index lama `(product_id, rack_id)` TIDAK menahan duplikat saat
 * rack_id NULL — MySQL menganggap setiap NULL berbeda, sehingga produk tanpa
 * rak bisa punya beberapa baris stok (dan qty-nya jadi terlihat dobel).
 * Index fungsional `(product_id, IFNULL(rack_id, 0))` menutup celah itu.
 *
 * Sebelum index dibuat, baris duplikat yang terlanjur ada digabung: qty
 * dijumlahkan ke baris id terkecil. Penjumlahan aman karena baris-baris itu
 * memang satu bucket yang sama (masing-masing menampung event penerimaannya
 * sendiri). Perbaikan yang lebih detail bisa dijalankan lebih dulu lewat
 * `php artisan stocks:audit` + `stocks:merge-duplicates --apply`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $merged = $this->mergeDuplicateRows();

        if ($merged > 0) {
            Log::warning("Migrasi stok: {$merged} baris stok duplikat digabung (produk × rak/RELAY yang sama).");
        }

        $this->createUniqueIndex();
    }

    public function down(): void
    {
        if ($this->supportsFunctionalIndex()) {
            DB::statement('DROP INDEX stocks_product_rack_null_safe_unique ON stocks');
        }
    }

    /**
     * Gabungkan baris dengan (product_id, IFNULL(rack_id,0)) yang sama.
     *
     * @return int jumlah baris yang dihapus
     */
    private function mergeDuplicateRows(): int
    {
        $groups = DB::table('stocks')
            ->selectRaw('product_id, IFNULL(rack_id, 0) AS rack_key, COUNT(*) AS total_rows')
            ->groupBy('product_id', DB::raw('IFNULL(rack_id, 0)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $removed = 0;

        foreach ($groups as $group) {
            $rows = DB::table('stocks')
                ->where('product_id', $group->product_id)
                ->when(
                    (int) $group->rack_key === 0,
                    fn ($q) => $q->whereNull('rack_id'),
                    fn ($q) => $q->where('rack_id', $group->rack_key)
                )
                ->orderBy('id')
                ->get(['id', 'quantity']);

            $keeper = $rows->first();
            $sum = (int) $rows->sum('quantity');

            DB::table('stocks')->where('id', $keeper->id)->update([
                'quantity' => $sum,
                'updated_at' => now(),
            ]);

            $others = $rows->slice(1)->pluck('id')->all();

            if (! empty($others)) {
                DB::table('stocks')->whereIn('id', $others)->delete();
                $removed += count($others);
            }
        }

        return $removed;
    }

    private function createUniqueIndex(): void
    {
        if (! $this->supportsFunctionalIndex()) {
            Log::warning('Migrasi stok: index unik NULL-safe dilewati (MySQL < 8.0.13). Jalankan `php artisan stocks:audit` berkala.');

            return;
        }

        $exists = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            ['stocks', 'stocks_product_rack_null_safe_unique']
        );

        if ((int) ($exists->total ?? 0) > 0) {
            return;
        }

        // Index fungsional: menganggap RELAY (NULL) sebagai satu nilai (0),
        // jadi satu produk hanya boleh punya satu baris stok per rak/RELAY.
        DB::statement('CREATE UNIQUE INDEX stocks_product_rack_null_safe_unique ON stocks (product_id, (IFNULL(rack_id, 0)))');
    }

    private function supportsFunctionalIndex(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $version = (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');

        // MariaDB melaporkan versi 10.x/11.x tapi TIDAK mendukung index fungsional.
        if (stripos($version, 'mariadb') !== false) {
            return false;
        }

        return version_compare(preg_replace('/[^0-9.].*$/', '', $version) ?: '0', '8.0.13', '>=');
    }
};
