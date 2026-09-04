<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemutihan data transaksi — hanya bisa dijalankan oleh yang punya akses VPS
 * (tanpa UI/permission tambahan). Menghapus SEMUA data gerak/transaksi dan
 * me-reset stok ke 0, tetapi MASTER data (produk, supplier, rak, lokasi,
 * user, role/menu) & jejak audit (activity_log) TETAP.
 */
class ResetTransactions extends Command
{
    protected $signature = 'db:reset-transactions
                            {--force : Skip konfirmasi}
                            {--keep-stock : Jangan reset quantity stok ke 0}
                            {--dry-run : Tampilkan yang akan dihapus tanpa menghapus apa pun}';

    protected $description = 'Pemutihan data transaksi — hapus semua transaksi & set stok 0 (master data tetap, activity_log tetap)';

    /**
     * Tabel transaksi/gerak — di-truncate (urut: child dulu, parent belakangan).
     */
    private const TRANSACTION_TABLES = [
        // Log koreksi & opname (baru)
        'stock_corrections',
        'stock_opname_items',
        'stock_opnames',

        // Cycle / penerimaan
        'receive_logs',
        'cycle_items',
        'cycles',

        // Shopping / pengiriman
        'shopping_items',
        'shoppings',

        // Riwayat import & notifikasi
        'import_logs',
        'notifications',
    ];

    /**
     * Antrian & cache — ikut dikosongkan (bukan bagian audit).
     */
    private const QUEUE_CACHE_TABLES = [
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
    ];

    public function handle(): int
    {
        $this->warn('╔════════════════════════════════════════════════════════════╗');
        $this->warn('║  PEMUTIHAN DATA TRANSAKSI                                  ║');
        $this->warn('║  • Hapus semua transaksi & riwayat gerak                   ║');
        $this->warn('║  • Set stok = 0 (master data & user TETAP)                 ║');
        $this->warn('║  • activity_log & file import TETAP (audit)                ║');
        $this->warn('╚════════════════════════════════════════════════════════════╝');

        $plan = $this->buildPlan();

        if ($this->option('dry-run')) {
            $this->info("\n🔍 DRY-RUN — tidak ada yang dihapus. Yang akan dikerjakan:");
            $this->printPlan($plan, '  akan di-TRUNCATE');
            if ($plan['reset_stock']) {
                $this->line('  • stocks.quantity  → di-set 0 (baris tetap ada)');
            }
            $this->newLine();

            return self::SUCCESS;
        }

        // ── Konfirmasi ────────────────────────────────────────
        if (! $this->option('force')) {
            $env = app()->environment();

            if ($env === 'production') {
                $confirm = $this->ask('⚠️  Environment PRODUCTION! Ketik "YA" untuk melanjutkan');
                if ($confirm !== 'YA') {
                    $this->info('Dibatalkan.');
                    return self::SUCCESS;
                }
            } elseif (! $this->confirm('Lanjutkan pemutihan data transaksi?', false)) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        // ── Truncate transaksi ────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($plan['truncate'] as $table) {
                DB::table($table)->truncate();
                $this->line("  ✓ truncate {$table}");
            }

            // ── Antrian & cache ──────────────────────────────
            foreach ($plan['queue_cache'] as $table) {
                DB::table($table)->truncate();
                $this->line("  ✓ truncate {$table}");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // ── Reset stok ke 0 ───────────────────────────────────
        if ($plan['reset_stock']) {
            $updated = DB::table('stocks')->update(['quantity' => 0]);
            $this->line("  ✓ stocks.quantity di-set 0 ({$updated} baris)");
        }

        $this->newLine();
        $this->info('✅ Pemutihan selesai!');
        $this->line('   Master data, user, role/menu, activity_log, dan file import TETAP.');
        $this->line('   Jalankan `php artisan optimize:clear` bila perlu, dan restart');
        $this->line('   queue worker bila ada antrean lama: docker compose ... restart queue');

        return self::SUCCESS;
    }

    private function buildPlan(): array
    {
        $truncate = [];
        foreach (self::TRANSACTION_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $truncate[] = $table;
            }
        }

        $queueCache = [];
        foreach (self::QUEUE_CACHE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $queueCache[] = $table;
            }
        }

        return [
            'truncate' => $truncate,
            'queue_cache' => $queueCache,
            'reset_stock' => ! $this->option('keep-stock') && Schema::hasTable('stocks'),
        ];
    }

    private function printPlan(array $plan, string $action): void
    {
        foreach ([$plan['truncate'], $plan['queue_cache']] as $group) {
            foreach ($group as $table) {
                $this->line("  • {$table} {$action}");
            }
        }
    }
}
