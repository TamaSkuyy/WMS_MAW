<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Shopping;
use App\Models\Supplier;
use App\Services\DataReset\DataResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemutihan riwayat per rentang tanggal: transaksi yang sudah tuntas
 * (cycle completed, shopping shipped/cripple/completed) harus ikut terhapus,
 * termasuk baris lama yang timestamp-nya NULL.
 */
class DataResetPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSupplier(): Supplier
    {
        return Supplier::factory()->create();
    }

    public function test_purge_deletes_completed_cycles_and_shipped_shoppings_in_range(): void
    {
        $supplier = $this->makeSupplier();

        $completedInRange = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => '2026-06-15 10:00:00',
        ]);

        $shippedInRange = Shopping::factory()->create([
            'status' => 'shipped',
            'shipped_at' => '2026-06-16 09:00:00',
        ]);

        $outside = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => '2026-05-01 10:00:00',
        ]);

        $draftCycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'received_at' => null,
        ]);
        $draftShopping = Shopping::factory()->create(['status' => 'draft', 'shipped_at' => null]);

        $service = new DataResetService();
        $stats = $service->purge('2026-06-01', '2026-06-30');

        $this->assertSame(1, $stats['cycles']);
        $this->assertSame(1, $stats['shoppings']);

        $this->assertDatabaseMissing('cycles', ['id' => $completedInRange->id]);
        $this->assertDatabaseMissing('shoppings', ['id' => $shippedInRange->id]);
        $this->assertDatabaseHas('cycles', ['id' => $outside->id]);
        $this->assertDatabaseHas('cycles', ['id' => $draftCycle->id]);
        $this->assertDatabaseHas('shoppings', ['id' => $draftShopping->id]);
    }

    public function test_purge_includes_rows_with_null_timestamps_using_fallback_date(): void
    {
        $supplier = $this->makeSupplier();

        // Data lama: status final tapi received_at/shipped_at NULL
        $legacyCycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => null,
            'delivery_date' => '2026-06-10',
        ]);

        $legacyShipped = Shopping::factory()->create([
            'status' => 'shipped',
            'shipped_at' => null,
            'shopping_date' => '2026-06-11',
        ]);

        $legacyCripple = Shopping::factory()->create([
            'status' => 'cripple',
            'shipped_at' => null,
            'shopping_date' => '2026-06-12',
        ]);

        $legacyCompleted = Shopping::factory()->create([
            'status' => 'completed',
            'shipped_at' => null,
            'shopping_date' => '2026-06-13',
        ]);

        $service = new DataResetService();
        $stats = $service->purge('2026-06-01', '2026-06-30');

        $this->assertSame(1, $stats['cycles']);
        $this->assertSame(3, $stats['shoppings']);

        $this->assertDatabaseMissing('cycles', ['id' => $legacyCycle->id]);
        $this->assertDatabaseMissing('shoppings', ['id' => $legacyShipped->id]);
        $this->assertDatabaseMissing('shoppings', ['id' => $legacyCripple->id]);
        $this->assertDatabaseMissing('shoppings', ['id' => $legacyCompleted->id]);
    }

    public function test_purge_counts_match_what_will_be_deleted(): void
    {
        $supplier = $this->makeSupplier();

        Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => null,
            'delivery_date' => '2026-06-10',
        ]);
        Shopping::factory()->create([
            'status' => 'shipped',
            'shipped_at' => null,
            'shopping_date' => '2026-06-11',
        ]);

        $service = new DataResetService();
        $counts = $service->purgeCounts('2026-01-01', '2026-12-31');

        $this->assertSame(1, $counts['cycles (diterima)']);
        $this->assertSame(1, $counts['shoppings (dikirim)']);
    }
}
