<?php

namespace Tests\Feature;

use App\Http\Controllers\StockOpnameController;
use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\Stock;
use App\Models\StockOpnameItem;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockRepairService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Stok dobel khususnya RELAY:
 *  - deteksi lewat buku besar transaksi (`StockLedger`, command `stocks:audit`),
 *  - pencegahan baris duplikat RELAY (unique index NULL-safe),
 *  - perbaikan data dari CLI (`stocks:set-quantity`, `stocks:merge-duplicates`).
 */
class StockRepairTest extends TestCase
{
    use RefreshDatabase;

    private function bucketQty(int $productId, ?int $rackId): int
    {
        return (int) Stock::where('product_id', $productId)
            ->when($rackId === null, fn ($q) => $q->whereNull('rack_id'), fn ($q) => $q->where('rack_id', $rackId))
            ->sum('quantity');
    }

    /** Terima $qty ke rak tertentu (null = RELAY) sekaligus isi stoknya. */
    private function receiveInto(?int $rackId, int $qty, ?Product $product = null): Product
    {
        $product ??= Product::factory()->create();

        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'rack_id' => $rackId,
            'quantity' => $qty,
            'received_quantity' => $qty,
        ]);

        Stock::create(['product_id' => $product->id, 'rack_id' => $rackId, 'quantity' => $qty]);

        return $product;
    }

    public function test_ledger_matches_receive_and_ship_flow(): void
    {
        $product = $this->receiveInto(null, 10);
        $ledger = new StockLedger();

        $this->assertSame([], $ledger->diffs(), 'Stok hasil terima harus cocok dengan buku besar.');

        $shopping = Shopping::factory()->create(['status' => 'shipped', 'shipped_at' => now()]);
        ShoppingItem::factory()->create([
            'shopping_id' => $shopping->id,
            'product_id' => $product->id,
            'rack_id' => null,
            'quantity' => 4,
        ]);
        Stock::where('product_id', $product->id)->whereNull('rack_id')->update(['quantity' => 6]);

        $this->assertSame([], $ledger->diffs(), 'Stok setelah kirim 4 harus cocok dengan buku besar.');

        // Drift buatan: stok sistem 9 padahal seharusnya 6.
        Stock::where('product_id', $product->id)->whereNull('rack_id')->update(['quantity' => 9]);

        $row = $ledger->diffs()[StockLedger::key($product->id, null)] ?? null;

        $this->assertNotNull($row, 'Selisih harus terdeteksi.');
        $this->assertSame(6, $row['expected']);
        $this->assertSame(9, $row['actual']);
        $this->assertSame(3, $row['diff']);
    }

    public function test_stock_opname_adjustment_is_counted_by_ledger(): void
    {
        $product = $this->receiveInto(null, 10);

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('stock opname'));

        $this->actingAs($user)
            ->postJson(route('stock-opname.apply'), [
                'rows' => [['part_number' => $product->part_number, 'rack_code' => '', 'actual_qty' => 8]],
            ])
            ->assertOk();

        $this->assertSame(8, $this->bucketQty($product->id, null));
        $this->assertSame([], (new StockLedger())->diffs(), 'Selisih opname harus ikut dihitung buku besar.');
    }

    public function test_manual_adjustment_is_counted_by_ledger(): void
    {
        $product = $this->receiveInto(null, 10);
        $ledger = new StockLedger();

        (new StockRepairService())->setQuantity($product->id, null, 0, 'hapus relay palsu untuk test');

        $this->assertSame(0, $this->bucketQty($product->id, null));
        $this->assertSame([], $ledger->diffs(), 'Penyesuaian manual harus ikut terhitung di buku besar.');
    }

    public function test_duplicate_relay_row_is_rejected_by_null_safe_unique_index(): void
    {
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 5]);

        $this->expectException(QueryException::class);

        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 7]);
    }

    public function test_merge_duplicates_refuses_bucket_without_duplicates(): void
    {
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 5]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada baris duplikat');

        (new StockRepairService())->mergeDuplicates($product->id, null, 'test');
    }

    public function test_merge_duplicates_command_reports_clean_database(): void
    {
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 5]);

        $this->artisan('stocks:merge-duplicates')->assertExitCode(0);
        $this->assertSame(5, $this->bucketQty($product->id, null));
    }

    public function test_set_quantity_is_dry_run_by_default_and_zero_deletes_relay_row(): void
    {
        $product = $this->receiveInto(null, 5);

        // Tanpa --apply: tidak ada yang berubah.
        $this->artisan('stocks:set-quantity', [
            '--product' => (string) $product->id,
            '--rack' => 'RELAY',
            '--qty' => '0',
            '--reason' => 'relay palsu (dry-run)',
        ])->assertExitCode(0);

        $this->assertSame(5, $this->bucketQty($product->id, null), 'Dry-run tidak boleh mengubah stok.');

        $this->artisan('stocks:set-quantity', [
            '--product' => (string) $product->id,
            '--rack' => 'RELAY',
            '--qty' => '0',
            '--reason' => 'barang sebenarnya sudah ada di rak',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Stock::where('product_id', $product->id)->count(), 'Baris RELAY harus dihapus.');
        $this->assertSame(1, StockOpnameItem::where('product_id', $product->id)->count(), 'Perbaikan harus tercatat di Riwayat Opname.');
    }

    public function test_set_quantity_requires_reason_when_applying(): void
    {
        $product = $this->receiveInto(null, 5);

        $this->artisan('stocks:set-quantity', [
            '--product' => (string) $product->id,
            '--rack' => 'RELAY',
            '--qty' => '0',
            '--reason' => 'x',
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertSame(5, $this->bucketQty($product->id, null), 'Tanpa alasan valid, stok tidak boleh berubah.');
    }

    public function test_set_quantity_can_move_relay_into_rack(): void
    {
        $rack = Rack::factory()->create(['code' => 'A-01']);
        $product = $this->receiveInto(null, 8);

        // Sudah ada stok 2 di rak tujuan → harus digabung jadi 10.
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $this->artisan('stocks:set-quantity', [
            '--product' => $product->part_number,
            '--rack' => 'RELAY',
            '--move-to' => 'A-01',
            '--reason' => 'pindahkan relay ke rak asli',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, $this->bucketQty($product->id, null));
        $this->assertSame(10, $this->bucketQty($product->id, $rack->id));
    }

    public function test_from_ledger_uses_transaction_ledger_value(): void
    {
        $rack = Rack::factory()->create(['code' => 'B-02']);
        $product = $this->receiveInto($rack->id, 10);

        // Stok di sistem salah (7), buku besar bilang 10.
        Stock::where('product_id', $product->id)->update(['quantity' => 7]);

        $this->artisan('stocks:set-quantity', [
            '--product' => (string) $product->id,
            '--rack' => 'B-02',
            '--from-ledger' => true,
            '--reason' => 'samakan dengan buku besar',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(10, $this->bucketQty($product->id, $rack->id));
        $this->assertSame([], (new StockLedger())->diffs());
    }

    public function test_stock_opname_warns_when_new_relay_row_would_duplicate_rack_stock(): void
    {
        $rack = Rack::factory()->create(['code' => 'C-03']);
        $product = Product::factory()->create(['part_number' => 'RELAY-TEST-01']);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

        $method = new \ReflectionMethod(StockOpnameController::class, 'resolveRows');
        $method->setAccessible(true);

        // File opname tanpa isi kolom RAK → dianggap RELAY.
        $rows = $method->invoke(new StockOpnameController(), [
            ['part_number' => 'RELAY-TEST-01', 'rack_code' => '', 'actual_qty' => 5],
        ]);

        $this->assertSame('new', $rows[0]['status']);
        $this->assertStringContainsString('SUDAH punya stok', $rows[0]['message']);
        $this->assertStringContainsString('C-03', $rows[0]['message']);
    }

    /** Opname tanpa kolom RAK pada produk yang stoknya sudah di rak → baris RELAY PALSU. */
    public function test_rackless_opname_creates_phantom_relay_and_fix_command_removes_it(): void
    {
        $rack = Rack::factory()->create(['code' => 'D-04']);
        $product = $this->receiveInto($rack->id, 5);

        $this->applyOpname($product->part_number, '', 5);

        $this->assertSame(5, $this->bucketQty($product->id, null), 'Opname membuat baris RELAY baru (dobel).');
        $this->assertSame(10, $this->bucketQty($product->id, null) + $this->bucketQty($product->id, $rack->id));

        $bucket = (new StockRepairService())->relayBuckets($product->id)[0] ?? null;
        $this->assertNotNull($bucket);
        $this->assertSame('PALSU', $bucket['verdict']);
        $this->assertSame(0, $bucket['inbound_relay'], 'Tidak pernah ada penerimaan ke RELAY.');
        $this->assertTrue($bucket['mirror']);
        $this->assertNotNull($bucket['opname'], 'Harus ada bukti dibuat opname.');

        // Dry-run dulu: tidak boleh berubah.
        $this->artisan('stocks:fix-phantom-relay')->assertExitCode(0);
        $this->assertSame(5, $this->bucketQty($product->id, null));

        $this->artisan('stocks:fix-phantom-relay', [
            '--reason' => 'hapus baris RELAY palsu hasil opname tanpa kolom RAK',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, $this->bucketQty($product->id, null), 'Baris RELAY palsu harus terhapus.');
        $this->assertSame(5, $this->bucketQty($product->id, $rack->id), 'Stok rak tidak boleh berubah.');
        $this->assertSame([], (new StockLedger())->diffs(), 'Buku besar harus tetap cocok setelah perbaikan.');
    }

    /** Baris RELAY yang benar-benar hasil penerimaan tanpa rak harus DIBIARKAN. */
    public function test_real_relay_stock_is_not_touched(): void
    {
        $rack = Rack::factory()->create(['code' => 'E-05']);
        $product = $this->receiveInto(null, 8);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $bucket = (new StockRepairService())->relayBuckets($product->id)[0] ?? null;
        $this->assertNotNull($bucket);
        $this->assertSame('SAH', $bucket['verdict']);

        $this->artisan('stocks:fix-phantom-relay', ['--apply' => true, '--reason' => 'coba hapus relay asli'])
            ->expectsOutputToContain('Tidak ada baris RELAY palsu')
            ->assertExitCode(0);

        $this->assertSame(8, $this->bucketQty($product->id, null), 'RELAY asli tidak boleh dihapus.');
    }

    /** Baris RELAY qty 0 hanya dibersihkan kalau diminta eksplisit. */
    public function test_clean_empty_removes_zero_qty_relay_rows(): void
    {
        $rack = Rack::factory()->create(['code' => 'F-06']);
        $product = Product::factory()->create(['part_number' => 'RELAY-TEST-03']);
        Stock::create(['product_id' => $product->id, 'rack_id' => null, 'quantity' => 0]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 4]);

        $this->artisan('stocks:fix-phantom-relay', ['--apply' => true, '--reason' => 'bersihkan relay kosong'])
            ->assertExitCode(0);
        $this->assertSame(1, Stock::where('product_id', $product->id)->whereNull('rack_id')->count(), 'Default tidak menyentuh baris 0 pcs.');

        $this->artisan('stocks:fix-phantom-relay', [
            '--clean-empty' => true,
            '--reason' => 'bersihkan baris relay 0 pcs',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Stock::where('product_id', $product->id)->whereNull('rack_id')->count());
        $this->assertSame(4, $this->bucketQty($product->id, $rack->id), 'Total stok tidak berubah.');
    }

    /** Kalau ada penyesuaian opname lanjutan, jangan dianggap PALSU — masuk PERIKSA. */
    public function test_relay_with_extra_opname_adjustment_is_review_only(): void
    {
        $rack = Rack::factory()->create(['code' => 'G-07']);
        $product = $this->receiveInto($rack->id, 5);

        $this->applyOpname($product->part_number, '', 5);   // membuat baris RELAY 5 (dobel)
        $this->applyOpname($product->part_number, '', 7);   // opname lanjutan → relay jadi 7

        $this->assertSame(7, $this->bucketQty($product->id, null));

        $bucket = (new StockRepairService())->relayBuckets($product->id)[0] ?? null;
        $this->assertNotNull($bucket);
        $this->assertSame('PERIKSA', $bucket['verdict'], 'Qty tidak sepenuhnya dijelaskan satu opname → jangan otomatis dihapus.');

        $this->artisan('stocks:fix-phantom-relay', ['--apply' => true, '--reason' => 'coba hapus yang periksa'])
            ->assertExitCode(0);

        $this->assertSame(7, $this->bucketQty($product->id, null), 'Status PERIKSA tidak boleh disentuh tanpa --include-review.');
    }

    /** Terapkan opname lewat endpoint (persis alur aplikasi). */
    private function applyOpname(string $partNumber, string $rackCode, int $actualQty): void
    {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('stock opname'));

        $this->actingAs($user)
            ->postJson(route('stock-opname.apply'), [
                'rows' => [['part_number' => $partNumber, 'rack_code' => $rackCode, 'actual_qty' => $actualQty]],
            ])
            ->assertOk();
    }
}
