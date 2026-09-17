<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Rack;
use App\Services\Stock\StockRepairService;
use Illuminate\Console\Command;

/**
 * Gabungkan baris stok duplikat untuk kunci yang sama (produk × rak/RELAY).
 *
 * Baris duplikat hanya mungkin terjadi pada RELAY (rack_id NULL), karena
 * MySQL menganggap NULL berbeda di unique index (product_id, rack_id).
 * Qty dijumlahkan ke baris id terkecil — baris-baris itu memang bucket yang
 * sama, jadi penjumlahannya benar. Setiap perbaikan dicatat sebagai baris
 * stock opname (`FIX-…`) sehingga muncul di Riwayat Opname aplikasi.
 *
 * Dry-run secara default: jalankan dengan --apply untuk benar-benar mengubah.
 */
class MergeDuplicateStocks extends Command
{
    protected $signature = 'stocks:merge-duplicates
                            {--product= : Batasi ke satu produk (ID atau part number)}
                            {--reason=Perbaikan baris stok duplikat : Alasan perbaikan (tercatat di riwayat)}
                            {--apply : Benar-benar mengubah data (tanpa ini hanya menampilkan rencana)}
                            {--force : Lewati konfirmasi di environment production}';

    protected $description = 'Gabungkan baris stok duplikat (produk × rak/RELAY sama) dengan menjumlahkan qty-nya';

    public function handle(StockRepairService $service): int
    {
        $productId = null;

        if ($filter = $this->option('product')) {
            $product = $service->resolveProduct((string) $filter);
            $productId = (int) $product->id;
            $this->line("Produk: #{$product->id} {$product->part_number} — {$product->name}");
        }

        $groups = $service->duplicateGroups($productId);

        if (empty($groups)) {
            $this->info('✅ Tidak ada baris stok duplikat. Tidak ada yang perlu diperbaiki.');
            return self::SUCCESS;
        }

        $products = Product::whereIn('id', collect($groups)->pluck('product_id'))
            ->get(['id', 'part_number', 'name'])->keyBy('id');
        $racks = Rack::pluck('code', 'id');

        $this->newLine();
        $this->table(
            ['produk', 'part_number', 'rak', 'baris', 'id:qty', 'total'],
            array_map(fn ($g) => [
                mb_substr($products[$g['product_id']]->name ?? '#'.$g['product_id'], 0, 30),
                $products[$g['product_id']]->part_number ?? '?',
                $g['rack_id'] === null ? 'RELAY' : ($racks[$g['rack_id']] ?? '#'.$g['rack_id']),
                $g['rows'],
                $g['detail'],
                $g['total'],
            ], $groups)
        );

        $totalRows = array_sum(array_column($groups, 'rows'));
        $this->line(sprintf('Ditemukan %d bucket duplikat (%d baris akan disatukan).', count($groups), $totalRows - count($groups)));

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('🔍 DRY-RUN — tidak ada yang diubah. Tambahkan --apply untuk menjalankan perbaikan.');
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

        $merged = 0;
        $removed = 0;
        $affected = 0;

        foreach ($groups as $g) {
            $result = $service->mergeDuplicates($g['product_id'], $g['rack_id'], $reason);
            $merged++;
            $removed += $result['merged_rows'];
            $affected += $result['total'];
        }

        $this->newLine();
        $this->info("✅ Selesai — {$merged} bucket digabung, {$removed} baris duplikat dihapus, total qty setelah rapi: {$affected}.");
        $this->line('   Jalankan `php artisan stocks:audit` untuk memastikan sudah bersih.');

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
