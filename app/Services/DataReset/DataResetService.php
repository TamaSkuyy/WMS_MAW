<?php

namespace App\Services\DataReset;

use App\Models\Cycle;
use App\Models\Shopping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logika pemutihan data transaksi — dipakai bersama oleh command artisan
 * (db:reset-transactions) dan halaman web Pemutihan Data (superadmin).
 *
 * Dua mode:
 *  - TANPA tanggal  → reset TOTAL: truncate semua transaksi + antrian/cache,
 *    stok di-set 0 (master, user, activity_log & file import TETAP).
 *  - DENGAN tanggal → purge RIWAYAT s/d tanggal X: hapus transaksi yang sudah
 *    tuntas (cycle diterima, shopping dikirim, opname, import log, koreksi,
 *    notifikasi) sampai tanggal tsb. STOK TIDAK disentuh (saldo tetap benar),
 *    antrian/cache tidak disentuh. Child dihapus otomatis via cascade FK.
 */
class DataResetService
{
    private const TRANSACTION_TABLES = [
        'stock_corrections',
        'stock_opname_items',
        'stock_opnames',
        'receive_logs',
        'cycle_items',
        'cycles',
        'shopping_items',
        'shoppings',
        'import_logs',
        'notifications',
    ];

    private const QUEUE_CACHE_TABLES = [
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
    ];

    private const SESSION_TABLE = 'sessions';

    // ── Mode reset total ─────────────────────────────────────────────────

    public function transactionTables(): array
    {
        return $this->existing(self::TRANSACTION_TABLES);
    }

    public function queueCacheTables(bool $includeSessions = true): array
    {
        $tables = $this->existing(self::QUEUE_CACHE_TABLES);

        if ($includeSessions && Schema::hasTable(self::SESSION_TABLE)) {
            $tables[] = self::SESSION_TABLE;
        }

        return $tables;
    }

    /** Jumlah record per tabel (mode reset total / tampilan awal). */
    public function counts(bool $includeSessions = true): array
    {
        $counts = [];

        foreach ([$this->transactionTables(), $this->queueCacheTables($includeSessions)] as $group) {
            foreach ($group as $table) {
                $counts[$table] = (int) DB::table($table)->count();
            }
        }

        $counts['stocks'] = (int) DB::table('stocks')->count();

        return $counts;
    }

    /**
     * Eksekusi reset total.
     *
     * @return array{tables: string[], stock_rows: int}
     */
    public function execute(bool $includeSessions = true, bool $zeroStock = true): array
    {
        $all = array_merge($this->transactionTables(), $this->queueCacheTables($includeSessions));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($all as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $stockRows = 0;
        if ($zeroStock && Schema::hasTable('stocks')) {
            $stockRows = (int) DB::table('stocks')->update(['quantity' => 0]);
        }

        return ['tables' => $all, 'stock_rows' => $stockRows];
    }

    // ── Mode purge riwayat RENTANG TANGGAL (stok TETAP) ───────────────────

    /**
     * Jumlah per tabel yang AKAN dihapus untuk purge rentang [from, to].
     * from/to boleh null (salah satu atau keduanya); keduanya null = mode total.
     */
    public function purgeCounts(?string $from = null, ?string $to = null): array
    {
        return [
            'cycles (diterima)' => $this->cycleScope($from, $to)->count(),
            'shoppings (dikirim)' => $this->shoppingScope($from, $to)->count(),
            'stock opnames' => $this->dateScope('stock_opnames', 'opname_date', $from, $to)->count(),
            'koreksi' => $this->dateScope('stock_corrections', 'created_at', $from, $to)->count(),
            'import logs' => $this->dateScope('import_logs', 'created_at', $from, $to)->count(),
            'notifikasi' => $this->dateScope('notifications', 'created_at', $from, $to)->count(),
        ];
    }

    /**
     * Eksekusi purge riwayat rentang [from, to]. Stok & antrian/cache TIDAK
     * disentuh. Item cycle/shopping & receive logs terhapus via cascade FK.
     *
     * @return array<string, int> jumlah baris dihapus per kelompok
     */
    public function purge(?string $from = null, ?string $to = null): array
    {
        $stats = [];

        // Cycle yang sudah diterima dalam rentang
        $cycleIds = $this->cycleScope($from, $to)->pluck('id');
        $stats['cycles'] = $cycleIds->count();
        if ($cycleIds->isNotEmpty()) {
            DB::table('cycles')->whereIn('id', $cycleIds)->delete();
            // koreksi yang menunjuk cycle terhapus
            DB::table('stock_corrections')->where('correctable_type', Cycle::class)->whereIn('correctable_id', $cycleIds)->delete();
        }

        // Shopping yang sudah dikirim dalam rentang
        $shoppingIds = $this->shoppingScope($from, $to)->pluck('id');
        $stats['shoppings'] = $shoppingIds->count();
        if ($shoppingIds->isNotEmpty()) {
            DB::table('shoppings')->whereIn('id', $shoppingIds)->delete();
            DB::table('stock_corrections')->where('correctable_type', Shopping::class)->whereIn('correctable_id', $shoppingIds)->delete();
        }

        // Stock opname (item cascade)
        $stats['stock opnames'] = $this->dateScope('stock_opnames', 'opname_date', $from, $to)->count();
        $this->dateScope('stock_opnames', 'opname_date', $from, $to)->delete();

        // Riwayat import & notifikasi
        $stats['import logs'] = $this->dateScope('import_logs', 'created_at', $from, $to)->count();
        $this->dateScope('import_logs', 'created_at', $from, $to)->delete();

        $stats['notifikasi'] = $this->dateScope('notifications', 'created_at', $from, $to)->count();
        $this->dateScope('notifications', 'created_at', $from, $to)->delete();

        // Koreksi lama + koreksi yatim (induknya sudah terhapus)
        $stats['koreksi'] = $this->dateScope('stock_corrections', 'created_at', $from, $to)->count();
        $this->dateScope('stock_corrections', 'created_at', $from, $to)->delete();

        DB::table('stock_corrections')
            ->where('correctable_type', Cycle::class)
            ->whereNotIn('correctable_id', DB::table('cycles')->select('id'))
            ->delete();
        DB::table('stock_corrections')
            ->where('correctable_type', Shopping::class)
            ->whereNotIn('correctable_id', DB::table('shoppings')->select('id'))
            ->delete();

        return $stats;
    }

    /** Cycle yang sudah diterima (received_at) dalam rentang tanggal. */
    private function cycleScope(?string $from, ?string $to)
    {
        return $this->dateScope('cycles', 'received_at', $from, $to)->whereNotNull('received_at');
    }

    /** Shopping yang sudah dikirim (shipped_at) dalam rentang tanggal. */
    private function shoppingScope(?string $from, ?string $to)
    {
        return $this->dateScope('shoppings', 'shipped_at', $from, $to)->whereNotNull('shipped_at');
    }

    private function dateScope(string $table, string $column, ?string $from, ?string $to)
    {
        $q = DB::table($table);

        if ($from !== null && $from !== '') {
            $q->whereDate($column, '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $q->whereDate($column, '<=', $to);
        }

        return $q;
    }

    private function existing(array $tables): array
    {
        return array_values(array_filter($tables, fn ($t) => Schema::hasTable($t)));
    }
}
