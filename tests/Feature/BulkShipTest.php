<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kirim massal "sekali klik setelah import":
 * planner mensimulasikan stok untuk SELURUH frame draft dulu (lookup & matching),
 * baru dikirim — frame yang stoknya kurang dilewati dan dilaporkan.
 */
class BulkShipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('ship shoppings'));
    }

    private function draft(string $frame, int $productId, ?int $rackId, int $qty, ?int $locationId = null): Shopping
    {
        $shopping = Shopping::factory()->create([
            'status' => 'draft',
            'frame_number' => $frame,
            'shopping_location_id' => $locationId,
        ]);

        $shopping->items()->create([
            'product_id' => $productId,
            'rack_id' => $rackId,
            'quantity' => $qty,
        ]);

        return $shopping;
    }

    private function preview(array $payload)
    {
        return $this->actingAs($this->user)->postJson(route('shoppings.bulk-ship.preview'), $payload);
    }

    public function test_preview_lists_all_ready_frames_for_all_drafts(): void
    {
        $rack = Rack::factory()->create(['code' => 'A-01']);
        $product = Product::factory()->create(['part_number' => 'PART-A']);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 50]);

        $this->draft('FRM-001', $product->id, $rack->id, 5);
        $this->draft('FRM-002', $product->id, $rack->id, 7);

        $response = $this->preview(['all' => true]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'summary' => ['total' => 2, 'ready' => 2, 'blocked' => 0, 'items' => 2, 'quantity' => 12],
        ]);

        $this->assertSame(['FRM-001', 'FRM-002'], array_column($response->json('ready'), 'frame_number'));
    }

    public function test_preview_accounts_for_shared_stock_between_frames(): void
    {
        $rack = Rack::factory()->create(['code' => 'B-02']);
        $product = Product::factory()->create(['part_number' => 'PART-B']);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 100]);

        $this->draft('FRM-001', $product->id, $rack->id, 60);
        $second = $this->draft('FRM-002', $product->id, $rack->id, 60);

        $response = $this->preview(['all' => true])->assertOk();

        $this->assertSame(1, $response->json('summary.ready'));
        $this->assertSame(1, $response->json('summary.blocked'));
        $this->assertSame($second->id, $response->json('blocked.0.id'));
        $this->assertStringContainsString('PART-B', $response->json('blocked.0.issues.0'));
        $this->assertStringContainsString('B-02', $response->json('blocked.0.issues.0'));
        $this->assertStringContainsString('kurang 20', $response->json('blocked.0.issues.0'));
    }

    public function test_bulk_ship_all_ships_ready_frames_and_skips_shortages(): void
    {
        $rack = Rack::factory()->create(['code' => 'C-03']);
        $product = Product::factory()->create(['part_number' => 'PART-C']);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $ok = $this->draft('FRM-OK', $product->id, $rack->id, 4);
        $short = $this->draft('FRM-SHORT', $product->id, $rack->id, 99);

        $response = $this->actingAs($this->user)->postJson(route('shoppings.bulk-ship'), [
            'all' => true,
            'only_ready' => true,
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'shipped' => 1,
            'skipped' => 1,
            'failed' => 0,
        ]);

        $this->assertSame('shipped', $ok->fresh()->status);
        $this->assertSame('draft', $short->fresh()->status);
        $this->assertSame(6, (int) Stock::where('product_id', $product->id)->value('quantity'));
        $this->assertStringContainsString('FRM-SHORT', $response->json('blocked.0.frame_number'));
    }

    public function test_bulk_ship_all_skips_frames_without_items(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

        $withItems = $this->draft('FRM-ADA', $product->id, $rack->id, 2);
        $empty = Shopping::factory()->create(['status' => 'draft', 'frame_number' => 'FRM-KOSONG']);

        $response = $this->actingAs($this->user)->postJson(route('shoppings.bulk-ship'), ['all' => true])
            ->assertOk();

        $this->assertSame(1, $response->json('shipped'));
        $this->assertSame(1, $response->json('skipped'));
        $this->assertSame('shipped', $withItems->fresh()->status);
        $this->assertSame('draft', $empty->fresh()->status);
        $this->assertStringContainsString('Belum ada barang', $response->json('blocked.0.issues.0'));
    }

    public function test_bulk_ship_all_fills_empty_location(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        $location = ShoppingLocation::create(['name' => 'Line A']);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 9]);

        $shopping = $this->draft('FRM-TAM', $product->id, $rack->id, 3);

        $this->actingAs($this->user)->postJson(route('shoppings.bulk-ship'), [
            'all' => true,
            'shopping_location_id' => $location->id,
        ])->assertOk()->assertJson(['shipped' => 1]);

        $this->assertSame('shipped', $shopping->fresh()->status);
        $this->assertSame($location->id, $shopping->fresh()->shopping_location_id);
    }

    public function test_bulk_ship_with_ids_still_returns_redirect_for_plain_form_post(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

        $shopping = $this->draft('FRM-OLD', $product->id, $rack->id, 2);

        $this->actingAs($this->user)->post(route('shoppings.bulk-ship'), ['ids' => [$shopping->id]])
            ->assertRedirect(route('shoppings.index'))
            ->assertSessionHas('success');

        $this->assertSame('shipped', $shopping->fresh()->status);
    }

    public function test_bulk_ship_without_scope_is_rejected(): void
    {
        $this->actingAs($this->user)->postJson(route('shoppings.bulk-ship'), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_preview_without_scope_is_rejected(): void
    {
        $this->preview([])->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_already_shipped_frames_are_not_included_in_all_scope(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

        $draft = $this->draft('FRM-DRAFT', $product->id, $rack->id, 1);
        $shipped = $this->draft('FRM-SHIPPED', $product->id, $rack->id, 1);
        $shipped->update(['status' => 'shipped', 'shipped_at' => now()]);

        $response = $this->actingAs($this->user)->postJson(route('shoppings.bulk-ship'), ['all' => true])
            ->assertOk();

        $this->assertSame(1, $response->json('shipped'));
        $this->assertSame([$draft->id], array_column($response->json('ready'), 'id'));
        $this->assertSame('shipped', $draft->fresh()->status);
        $this->assertSame(4, (int) Stock::where('product_id', $product->id)->value('quantity'));
    }
}
