<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\ReceiveLog;
use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hapus massal transaksi (superadmin) + koreksi stok otomatis.
 */
class BulkDeleteTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $user->givePermissionTo(Permission::findOrCreate('delete shoppings'));
        $user->givePermissionTo(Permission::findOrCreate('delete cycles'));

        return $user;
    }

    private function stockOf(int $productId, ?int $rackId): int
    {
        return (int) Stock::where('product_id', $productId)
            ->when($rackId === null, fn ($q) => $q->whereNull('rack_id'), fn ($q) => $q->where('rack_id', $rackId))
            ->value('quantity');
    }

    public function test_bulk_delete_shipped_shopping_restores_stock(): void
    {
        $admin = $this->superadmin();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $shopping = Shopping::factory()->create(['status' => 'shipped', 'shipped_at' => now()]);
        ShoppingItem::factory()->create([
            'shopping_id' => $shopping->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($admin)->postJson(route('shoppings.bulk-delete'), ['ids' => [$shopping->id]]);

        $response->assertOk()->assertJson(['ok' => true, 'deleted' => 1, 'stock_restored' => 5]);
        $this->assertDatabaseMissing('shoppings', ['id' => $shopping->id]);
        $this->assertSame(15, $this->stockOf($product->id, $rack->id));
    }

    public function test_bulk_delete_draft_shopping_does_not_change_stock(): void
    {
        $admin = $this->superadmin();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $shopping = Shopping::factory()->create(['status' => 'draft', 'shipped_at' => null]);
        ShoppingItem::factory()->create([
            'shopping_id' => $shopping->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 3,
        ]);

        $this->actingAs($admin)->postJson(route('shoppings.bulk-delete'), ['ids' => [$shopping->id]])
            ->assertOk();

        $this->assertDatabaseMissing('shoppings', ['id' => $shopping->id]);
        $this->assertSame(10, $this->stockOf($product->id, $rack->id));
    }

    public function test_bulk_delete_completed_cycle_reduces_stock_from_receive_logs(): void
    {
        $admin = $this->superadmin();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => now(),
        ]);
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'received_quantity' => 4,
            'rack_id' => $rack->id,
        ]);
        ReceiveLog::create([
            'cycle_item_id' => $item->id,
            'quantity' => 4,
            'rack_id' => $rack->id,
            'user_id' => $admin->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson(route('cycles.bulk-delete'), ['ids' => [$cycle->id]]);

        $response->assertOk()->assertJson(['ok' => true, 'deleted' => 1, 'stock_reduced' => 4]);
        $this->assertDatabaseMissing('cycles', ['id' => $cycle->id]);
        $this->assertSame(6, $this->stockOf($product->id, $rack->id));
    }

    public function test_bulk_delete_cycle_clamps_stock_at_zero_and_reports_shortage(): void
    {
        $admin = $this->superadmin();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => now(),
        ]);
        CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'received_quantity' => 4,
            'rack_id' => $rack->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('cycles.bulk-delete'), ['ids' => [$cycle->id]]);

        $response->assertOk()->assertJson(['deleted' => 1, 'stock_reduced' => 2, 'stock_shortage' => 2]);
        $this->assertSame(0, $this->stockOf($product->id, $rack->id));
    }

    public function test_bulk_delete_requires_superadmin(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('delete shoppings'));

        $shopping = Shopping::factory()->create(['status' => 'draft']);

        $this->actingAs($user)
            ->postJson(route('shoppings.bulk-delete'), ['ids' => [$shopping->id]])
            ->assertForbidden();
    }
}
