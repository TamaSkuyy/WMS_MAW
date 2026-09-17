<?php

namespace App\Console\Commands;

use App\Services\Stock\StockRepairService;
use Illuminate\Console\Command;

/**
 * Hapus baris RELAY PALSU — baris stok tanpa rak yang qty-nya BUKAN hasil
 * penerimaan, melainkan dibuat oleh stock opname yang file-nya kehilangan
 * kolom RAK (sehingga baris RELAY lahir berdampingan dengan baris rak yang
 * sudah ada ⇒ stok terlihat dobel).
 *
 * Kriteria PALSU (dihitung `StockRepairService::relayBuckets()`):
 *   1. baris RELAY berisi qty > 0;
 *   2. TIDAK pernah ada penerimaan yang masuk ke RELAY (buku besar = 0);
 *   3. ada bukti dibuat oleh stock opname (baris opname qty sistem 0 → dibuat baru);
 *   4. produk sudah punya stok di rak (jadi qty fisiknya tetap terhitung).
 *
 * Baris RELAY yang SAH (memang pernah diterima tanpa rak / overflow) TIDAK
 * disentuh, sebesar apa pun qty-nya.
 *
 * Dry-run secara default. Perbaikan dicatat sebagai baris stock opname `FIX-…`
 * dengan alasan di kolom notes (muncul di Riwayat Opname, buku besar tetap cocok).
 */
class FixPhantomRelay extends Command
{
    protected $signature = 'stocks:fix-phantom-relay
                            {--product= : Batasi ke satu produk (ID / part number)}
                            {--clean-empty : Sekalian hapus baris RELAY qty 0 (tidak mengubah total stok)}
                            {--include-review : Ikut menolkan yang berstatus PERIKSA (hati-hati — baca daftarnya)}
                            {--reason=Hapus baris RELAY palsu (tidak ada penerimaan ke RELAY) : Alasan perbaikan}
                            {--apply : Benar-benar mengubah data (tanpa ini hanya menampilkan rencana)}
                            {--force : Lewati konfirmasi di environment production}';

    protected $description = 'Hapus baris RELAY palsu (dobel) hasil stock opname tanpa kolom RAK';

    public function handle(StockRepairService $service): int
    {
        $productId = null;

        if ($filter = $this->option('product')) {
            try {
                $product = $service->resolveProduct((string) $filter);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }

            $productId = (int) $product->id;
            $this->line("Produk: #{$product->id} {$product->part_number} — {$product->name}");
        }

        $verdicts = ['PALSU'];

        if ($this->option('include-review')) {
            $verdicts[] = 'PERIKSA';
        }

        if ($this->option('clean-empty')) {
            $verdicts[] = 'KOSONG';
        }

        $buckets = array_values(array_filter(
            $service->relayBuckets($productId),
            fn ($row) => in_array($row['verdict'], $verdicts, true)
        ));

        if (empty($buckets)) {
            $this->info('✅ Tidak ada baris RELAY palsu. Tidak ada yang perlu diperbaiki.');
            return self::SUCCESS;
        }

        $totalQty = array_sum(array_map(fn ($r) => $r['relay'], $buckets));

        $this->newLine();
        $this->table(
            ['part_number', 'produk', 'relay', 'rak (tetap)', 'bukti opname', 'verdict'],
            array_map(fn ($r) => [
                $r['part_number'],
                mb_substr($r['product_name'], 0, 30),
                $r['relay'],
                mb_substr($r['rack_detail'], 0, 30),
                $r['opname'] ?? '-',
                $r['verdict'],
            ], $buckets)
        );

        $this->line(sprintf(
            '%d baris RELAY akan dihapus (total %s pcs). Baris rak TIDAK diubah, jadi stok fisik tetap terhitung.',
            count($buckets),
            number_format($totalQty, 0, ',', '.')
        ));
        $this->line(sprintf('Total stok sistem akan turun %s pcs (itu memang qty dobelnya).', number_format($totalQty, 0, ',', '.')));

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('🔍 DRY-RUN — tidak ada yang diubah. Tambahkan --apply untuk menjalankan.');
            return self::SUCCESS;
        }

        $reason = trim((string) $this->option('reason'));
        if (mb_strlen($reason) < 5) {
            $this->error('--reason minimal 5 karakter (dipakai sebagai catatan audit).');
            return self::FAILURE;
        }

        if (! $this->confirmProduction()) {
            return self::SUCCESS;
        }

        $done = 0;
        $removed = 0;

        foreach ($buckets as $row) {
            try {
                $service->setQuantity($row['product_id'], null, 0, $reason);
                $done++;
                $removed += $row['relay'];
            } catch (\Throwable $e) {
                $this->error(sprintf('Gagal untuk %s: %s', $row['part_number'], $e->getMessage()));
            }
        }

        $this->newLine();
        $this->info(sprintf('✅ %d baris RELAY palsu dihapus, %s pcs qty dobel dibuang.', $done, number_format($removed, 0, ',', '.')));
        $this->line('   Periksa lagi: php artisan stocks:audit');

        return self::SUCCESS;
    }

    private function confirmProduction(): bool
    {
        if ($this->option('force') || app()->environment() !== 'production') {
            return true;
        }

        return $this->ask('⚠️  Environment PRODUCTION! Ketik "YA" untuk melanjutkan') === 'YA';
    }
}
