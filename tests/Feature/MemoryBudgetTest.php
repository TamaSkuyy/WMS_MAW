<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\ShoppingLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exceptions\ExportException;
use App\Services\ImportExport\Managers\ExportManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Penjaga "budget memori" request: regresi dari
 * "Allowed memory size of 134217728 bytes exhausted" di
 * Illuminate\...\Relations\BelongsTo.php — yaitu saat satu request memuat
 * koleksi model yang tidak dibatasi lalu meng-eager-load relasi belongsTo.
 */
class MemoryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions = []): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    /**
     * Ringkasan laporan HARUS dihitung di SQL. Dulu `receivingSummary()`/`shoppingSummary()`
     * memakai query ber-`with()` lalu `->get()` atas seluruh riwayat hanya untuk
     * menghitung 3 angka.
     */
    public function test_receiving_report_summary_does_not_select_unbounded_rows(): void
    {
        $user = $this->userWith(['view receiving report']);
        $this->seedCycleItems(3, 20);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)->get(route('reports.receiving'))->assertOk();

        $this->assertItemQueriesAreBoundedOrAggregate($queries, 'cycle_items');
    }

    public function test_shopping_report_summary_does_not_select_unbounded_rows(): void
    {
        $user = $this->userWith(['view shopping report']);
        $this->seedShoppingItems(3, 20);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)->get(route('reports.shopping'))->assertOk();

        $this->assertItemQueriesAreBoundedOrAggregate($queries, 'shopping_items');
    }

    /** Halaman index Shopping hanya butuh jumlah item, bukan baris itemnya. */
    public function test_shopping_index_does_not_send_item_rows(): void
    {
        $user = $this->userWith(['view shoppings']);
        $product = Product::factory()->create();
        $shopping = Shopping::factory()->create(['status' => 'draft', 'frame_number' => 'FR-MEM', 'shopping_location_id' => null]);
        ShoppingItem::factory()->create([
            'shopping_id' => $shopping->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user)->get(route('shoppings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Shopping/Index')
                ->where('shoppings.data.0.items_count', 1)
                ->missing('shoppings.data.0.items'));
    }

    /** lastUsedRacks: tetap benar, tapi hasilnya dibatasi jumlah produk di cycle. */
    public function test_cycle_show_returns_last_used_rack_from_another_cycle(): void
    {
        $user = $this->userWith(['view cycles']);
        $product = Product::factory()->create();
        $rackOld = Rack::factory()->create();
        $rackNew = Rack::factory()->create();

        // Dua cycle berbeda untuk produk yang sama (cycle_items unik per cycle+produk):
        // yang paling baru harus menang.
        $older = Cycle::factory()->create(['status' => 'completed']);
        CycleItem::factory()->create([
            'cycle_id' => $older->id,
            'product_id' => $product->id,
            'rack_id' => $rackOld->id,
            'updated_at' => now()->subDays(2),
        ]);

        $newer = Cycle::factory()->create(['status' => 'completed']);
        CycleItem::factory()->create([
            'cycle_id' => $newer->id,
            'product_id' => $product->id,
            'rack_id' => $rackNew->id,
            'updated_at' => now()->subDay(),
        ]);

        $current = Cycle::factory()->create(['status' => 'draft']);
        CycleItem::factory()->create([
            'cycle_id' => $current->id,
            'product_id' => $product->id,
            'rack_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('cycles.show', $current));

        $response->assertOk();

        $props = json_decode(json_encode($response->viewData('page')), true)['props'];
        $this->assertSame($rackNew->id, (int) $props['lastUsedRacks'][$product->id]);
    }

    /** Tandai semua notifikasi dibaca = satu UPDATE, bukan hidrasi semua baris. */
    public function test_mark_all_notifications_read_uses_single_update(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\ImportCompletedNotification',
                'data' => ['message' => 'tes'],
                'read_at' => null,
            ]);
        }

        $updates = 0;
        DB::listen(function ($query) use (&$updates) {
            if (str_starts_with(strtolower(trim($query->sql)), 'update `notifications`')) {
                $updates++;
            }
        });

        $this->actingAs($user)->post(route('notifications.markAllRead'))->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $updates, 'Semua notifikasi harus ditandai lewat satu query UPDATE.');
    }

    /** Guard: xlsx/pdf dibangun penuh di memori → tolak kalau barisnya kebanyakan. */
    public function test_xlsx_export_over_row_limit_is_rejected_with_message(): void
    {
        $exporter = $this->hugeCrossJoinExporter();
        $config = new ExportConfig(
            format: ExportFormat::Xlsx,
            fileName: 'test-export',
            headings: ['a'],
            columns: [],
            exportableClass: $exporter::class,
        );

        $this->expectException(ExportException::class);

        app(ExportManager::class)->download($exporter, $config);
    }

    /** CSV di-stream per halaman — semua baris tetap keluar walau lebih dari satu halaman. */
    public function test_csv_export_streams_every_row_across_pages(): void
    {
        $supplier = Supplier::factory()->create();
        $cycle = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed']);

        // cycle_items unik per (cycle_id, product_id) → tiap baris pakai produk sendiri.
        $products = Product::factory()->count(600)->create();

        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                'cycle_id' => $cycle->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'received_quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        CycleItem::insert($rows); // 600 > EXPORT_CHUNK (500) → melewati 2 halaman

        $exporter = new class extends BaseExporter {
            public function headings(): array
            {
                return ['cycle_id', 'product_id', 'qty'];
            }

            public function exportQuery(): Builder
            {
                return CycleItem::query()->with('cycle')->orderBy('id');
            }

            public function mapRow($model): array
            {
                return [$model->cycle_id, $model->product_id, $model->received_quantity];
            }
        };

        $config = new ExportConfig(
            format: ExportFormat::Csv,
            fileName: 'cycle-items-test',
            headings: $exporter->headings(),
            columns: [],
            exportableClass: $exporter::class,
        );

        $response = app(ExportManager::class)->download($exporter, $config);

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $lines = array_values(array_filter(explode("\n", trim($csv)), fn ($line) => $line !== ''));

        $this->assertSame(601, count($lines), '1 header + 600 baris harus terkirim semua.');
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function seedCycleItems(int $cycles, int $itemsPerCycle): void
    {
        $supplier = Supplier::factory()->create();

        for ($c = 0; $c < $cycles; $c++) {
            $cycle = Cycle::factory()->create([
                'supplier_id' => $supplier->id,
                'status' => 'completed',
                'received_at' => now(),
            ]);

            for ($i = 0; $i < $itemsPerCycle; $i++) {
                CycleItem::factory()->create([
                    'cycle_id' => $cycle->id,
                    'product_id' => Product::factory()->create()->id,
                    'received_quantity' => 1,
                ]);
            }
        }
    }

    private function seedShoppingItems(int $shoppings, int $itemsPerShopping): void
    {
        $location = ShoppingLocation::create(['name' => 'LINE MEM']);

        for ($s = 0; $s < $shoppings; $s++) {
            $shopping = Shopping::factory()->create([
                'shopping_location_id' => $location->id,
                'status' => 'shipped',
                'frame_number' => 'FR-MEM-' . $s,
            ]);

            for ($i = 0; $i < $itemsPerShopping; $i++) {
                ShoppingItem::factory()->create([
                    'shopping_id' => $shopping->id,
                    'product_id' => Product::factory()->create()->id,
                    'quantity' => 1,
                ]);
            }
        }
    }

    /** Semua `select *` dari tabel item wajib punya LIMIT; sisanya harus agregat. */
    private function assertItemQueriesAreBoundedOrAggregate(array $queries, string $table): void
    {
        $itemQueries = array_values(array_filter(
            $queries,
            fn (string $sql) => str_contains($sql, "from `{$table}`") || str_contains($sql, "from {$table}")
        ));

        $this->assertNotEmpty($itemQueries, "Tidak ada query ke {$table} yang tercatat.");

        foreach ($itemQueries as $sql) {
            $normalized = strtolower($sql);

            if (str_starts_with(ltrim($normalized), 'select *')) {
                $this->assertStringContainsString(
                    'limit',
                    $normalized,
                    "Query {$table} tanpa batas (select * tanpa limit) — bisa menghabiskan memori: {$sql}"
                );

                continue;
            }

            $this->assertMatchesRegularExpression(
                '/select (count|sum|max|min|avg)\(/i',
                $sql,
                "Query {$table} bukan agregat dan bukan halaman ber-limit: {$sql}"
            );
        }
    }

    /** Query yang count()-nya besar tanpa perlu meng-insert ribuan baris. */
    private function hugeCrossJoinExporter(): BaseExporter
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $cycle = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed']);

            // Unik per (cycle_id, product_id) → satu produk berbeda tiap baris.
            foreach (Product::factory()->count(40)->create() as $rowProduct) {
                CycleItem::factory()->create([
                    'cycle_id' => $cycle->id,
                    'product_id' => $rowProduct->id,
                    'received_quantity' => 1,
                ]);
            }
        }

        // 25 cycle × 1.000 item = 25.000 baris > batas xlsx (20.000).
        return new class extends BaseExporter {
            public function headings(): array
            {
                return ['a'];
            }

            public function exportQuery(): Builder
            {
                // SQLite/MySQL: count() hasil crossJoin = perkalian baris.
                return Cycle::query()->crossJoin('cycle_items');
            }

            public function mapRow($model): array
            {
                return ['x'];
            }
        };
    }
}
