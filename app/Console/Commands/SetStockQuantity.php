<?php

namespace App\Console\Commands;

use App\Models\Rack;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockRepairService;
use Illuminate\Console\Command;

/**
 * Perbaikan qty satu bucket stok (produk × rak/RELAY) — primitif untuk
 * merapikan data yang dobel:
 *
 *   # hapus baris RELAY palsu (barangnya sebenarnya ada di rak)
 *   php artisan stocks:set-quantity --product=P5162-0KA08 --rack=RELAY --qty=0 --reason="..." --apply
 *
 *   # pindahkan isi RELAY ke rak aslinya
 *   php artisan stocks:set-quantity --product=123 --rack=RELAY --move-to=A1 --reason="..." --apply
 *
 *   # samakan dengan hitungan buku besar transaksi
 *   php artisan stocks:set-quantity --product=123 --rack=RELAY --from-ledger --reason="..." --apply
 *
 * Dry-run secara default. Semua perubahan dicatat sebagai baris STOCK OPNAME
 * (`FIX-…`) dengan alasan di kolom notes — muncul di Riwayat Opname aplikasi dan
 * tetap konsisten dengan buku besar (`StockLedger`).
 */
class SetStockQuantity extends Command
{
    protected $signature = 'stocks:set-quantity
                            {--product= : ID atau part number produk (wajib)}
                            {--rack=RELAY : Bucket sumber: kode rak, #id, atau RELAY/tanpa rak}
                            {--qty= : Qty absolut tujuan (wajib kecuali --move-to tanpa --qty)}
                            {--from-ledger : Pakai qty dari buku besar transaksi (mengabaikan --qty)}
                            {--move-to= : Pindahkan qty bucket ini ke rak lain (kode rak / RELAY)}
                            {--reason= : Alasan perbaikan (wajib saat --apply, min 5 karakter)}
                            {--apply : Benar-benar mengubah data (tanpa ini hanya menampilkan rencana)}
                            {--force : Lewati konfirmasi di environment production}';

    protected $description = 'Set/pindahkan qty satu bucket stok (produk × rak/RELAY) — untuk merapikan data stok dobel';

    public function handle(StockRepairService $service): int
    {
        try {
            $product = $service->resolveProduct((string) $this->option('product'));
            $source = $service->resolveRack((string) $this->option('rack'));
            $target = $this->option('move-to') !== null
                ? $service->resolveRack((string) $this->option('move-to'))
                : false;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $ledger = new StockLedger();
        $expected = $ledger->expected();
        $rackLabel = fn (?int $rackId) => $rackId === null ? 'RELAY' : (string) (Rack::whereKey($rackId)->value('code') ?? '#'.$rackId);

        $sourceQty = (int) ($service->bucketRows($product->id, $source?->id)->sum('quantity'));
        $ledgerSource = (int) ($expected[StockLedger::key((int) $product->id, $source?->id)] ?? 0);

        $this->newLine();
        $this->line(sprintf('Produk : #%d %s — %s', $product->id, $product->part_number, $product->name));
        $this->line(sprintf('Bucket : %s (qty sistem saat ini: %d, buku besar: %d)', $rackLabel($source?->id), $sourceQty, $ledgerSource));

        if ($target !== false) {
            $targetQty = (int) ($service->bucketRows($product->id, $target?->id)->sum('quantity'));
            $moveQty = $this->option('qty') !== null ? (int) $this->option('qty') : $sourceQty;

            if ($moveQty <= 0) {
                $this->error(sprintf('Tidak ada qty untuk dipindah di %s (qty saat ini %d).', $rackLabel($source?->id), $sourceQty));
                return self::FAILURE;
            }

            $this->line(sprintf('Pindah : %s → %s sebanyak %d (tujuan sekarang: %d → %d)',
                $rackLabel($source?->id), $rackLabel($target?->id), $moveQty, $targetQty, $targetQty + $moveQty));

            if (! $this->option('apply')) {
                $this->newLine();
                $this->warn('🔍 DRY-RUN — tidak ada yang diubah. Tambahkan --apply untuk menjalankan.');
                return self::SUCCESS;
            }

            $reason = $this->requireReason();
            if ($reason === null || ! $this->confirmProduction()) {
                return $reason === null ? self::FAILURE : self::SUCCESS;
            }

            try {
                $result = $service->move((int) $product->id, $source?->id, $target?->id, $moveQty, $reason);
            } catch (\Throwable $e) {
                $this->error('Gagal: '.$e->getMessage());
                return self::FAILURE;
            }

            $this->info(sprintf('✅ %d pcs dipindah dari %s ke %s. Sisa %s: %d, %s: %d.',
                $result['moved'], $rackLabel($source?->id), $rackLabel($target?->id),
                $rackLabel($source?->id), $result['source_after'], $rackLabel($target?->id), $result['target_after']));

            return self::SUCCESS;
        }

        $target_qty = $this->option('from-ledger')
            ? $ledgerSource
            : ($this->option('qty') !== null ? (int) $this->option('qty') : null);

        if ($target_qty === null) {
            $this->error('Isi --qty=N, atau --from-ledger, atau --move-to=KODE_RAK.');
            return self::FAILURE;
        }

        if ($target_qty < 0) {
            $this->error('--qty tidak boleh negatif.');
            return self::FAILURE;
        }

        $this->line(sprintf('Rencana: %s = %d → %d%s', $rackLabel($source?->id), $sourceQty, $target_qty,
            $target_qty === 0 ? ' (baris bucket akan DIHAPUS)' : ''));

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('🔍 DRY-RUN — tidak ada yang diubah. Tambahkan --apply untuk menjalankan.');
            return self::SUCCESS;
        }

        $reason = $this->requireReason();
        if ($reason === null || ! $this->confirmProduction()) {
            return $reason === null ? self::FAILURE : self::SUCCESS;
        }

        try {
            $service->setQuantity((int) $product->id, $source?->id, $target_qty, $reason);
        } catch (\Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('✅ %s sekarang %d (sebelumnya %d).', $rackLabel($source?->id), $target_qty, $sourceQty));

        return self::SUCCESS;
    }

    private function requireReason(): ?string
    {
        $reason = trim((string) $this->option('reason'));

        if (mb_strlen($reason) < 5) {
            $this->error('--reason wajib diisi (min 5 karakter) supaya perbaikan bisa diaudit.');
            return null;
        }

        return $reason;
    }

    private function confirmProduction(): bool
    {
        if ($this->option('force') || app()->environment() !== 'production') {
            return true;
        }

        return $this->ask('⚠️  Environment PRODUCTION! Ketik "YA" untuk melanjutkan') === 'YA';
    }
}
