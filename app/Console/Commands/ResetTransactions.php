<?php

namespace App\Console\Commands;

use App\Services\DataReset\DataResetService;
use Illuminate\Console\Command;

/**
 * Pemutihan data transaksi — hanya bisa dijalankan oleh yang punya akses VPS.
 * Tiga mode (sama dengan halaman web "Pemutihan Data"):
 *  - tanpa tanggal          : reset TOTAL (transaksi + antrian/cache, stok → 0)
 *  - --until                : purge riwayat s/d tanggal (stok TETAP)
 *  - --from / --from+--until: purge riwayat rentang tanggal (stok TETAP)
 * Master data, user, activity_log & file import selalu TETAP.
 */
class ResetTransactions extends Command
{
    protected $signature = 'db:reset-transactions
                            {--force : Skip konfirmasi}
                            {--keep-stock : Jangan reset quantity stok ke 0 (hanya utk mode total)}
                            {--from= : Purge riwayat DARI tanggal YYYY-MM-DD — stok & antrian TETAP}
                            {--until= : Purge riwayat SAMPAI tanggal YYYY-MM-DD — stok & antrian TETAP}
                            {--dry-run : Tampilkan yang akan dihapus tanpa menghapus apa pun}';

    protected $description = 'Pemutihan data transaksi — reset total (stok 0) atau purge riwayat rentang tanggal (stok tetap)';

    public function handle(): int
    {
        $service = new DataResetService();
        $from = $this->option('from') ?: null;
        $until = $this->option('until') ?: null;

        foreach (['from' => $from, 'until' => $until] as $name => $value) {
            if ($value !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->error("Format --{$name} salah. Contoh: --{$name}=2026-08-31");
                return self::FAILURE;
            }
        }

        $rangeMode = $from !== null || $until !== null;

        $this->warn('╔════════════════════════════════════════════════════════════╗');
        if ($rangeMode) {
            $range = trim(($from ?? '').' — '.($until ?? ''), ' —');
            $this->warn('║  PEMUTIHAN RIWAYAT '.str_pad($range, 44, ' ', STR_PAD_RIGHT).'║');
            $this->warn('║  • Hapus transaksi tuntas dalam rentang (stok TETAP)    ║');
        } else {
            $this->warn('║  PEMUTIHAN DATA TRANSAKSI (TOTAL)                       ║');
            $this->warn('║  • Hapus semua transaksi & riwayat gerak                ║');
            $this->warn('║  • Set stok = 0                                         ║');
        }
        $this->warn('║  • master data, user, activity_log & file TETAP        ║');
        $this->warn('╚════════════════════════════════════════════════════════════╝');

        // ── Dry run ──────────────────────────────────────────────
        if ($this->option('dry-run')) {
            $this->info("\n🔍 DRY-RUN — tidak ada yang dihapus.");

            if ($rangeMode) {
                foreach ($service->purgeCounts($from, $until) as $label => $n) {
                    $this->line("  • {$label}: {$n} baris");
                }
                $this->line('  • stok TIDAK diubah; antrian/cache TIDAK disentuh');
            } else {
                foreach (array_merge($service->transactionTables(), $service->queueCacheTables()) as $table) {
                    $this->line("  • {$table}  akan di-TRUNCATE");
                }
                if (! $this->option('keep-stock')) {
                    $this->line('  • stocks.quantity → di-set 0 (baris tetap ada)');
                }
            }
            $this->newLine();

            return self::SUCCESS;
        }

        // ── Konfirmasi ───────────────────────────────────────────
        if (! $this->option('force')) {
            $env = app()->environment();
            $range = trim(($from ?? '').' — '.($until ?? ''), ' —');
            $question = $rangeMode
                ? "Hapus riwayat transaksi {$range}? (stok tetap)"
                : 'Lanjutkan pemutihan data transaksi (reset total)?';

            if ($env === 'production') {
                $confirm = $this->ask('⚠️  Environment PRODUCTION! Ketik "YA" untuk melanjutkan');
                if ($confirm !== 'YA') {
                    $this->info('Dibatalkan.');
                    return self::SUCCESS;
                }
            } elseif (! $this->confirm($question, false)) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        // ── Eksekusi ─────────────────────────────────────────────
        if ($rangeMode) {
            $stats = $service->purge($from, $until);
            foreach ($stats as $label => $n) {
                $this->line("  ✓ {$label}: {$n} baris dihapus");
            }
            $range = trim(($from ?? '').' — '.($until ?? ''), ' —');
            $this->newLine();
            $this->info("✅ Selesai — riwayat {$range} dibersihkan, stok TIDAK diubah.");
        } else {
            $result = $service->execute(includeSessions: true, zeroStock: ! $this->option('keep-stock'));
            foreach ($result['tables'] as $table) {
                $this->line("  ✓ truncate {$table}");
            }
            if (! $this->option('keep-stock')) {
                $this->line("  ✓ stocks.quantity di-set 0 ({$result['stock_rows']} baris)");
            }
            $this->newLine();
            $this->info('✅ Pemutihan selesai!');
            $this->line('   Master data, user, role/menu, activity_log, dan file import TETAP.');
        }

        return self::SUCCESS;
    }
}
