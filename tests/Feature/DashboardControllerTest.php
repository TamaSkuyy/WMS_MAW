<?php

namespace Tests\Feature;

use App\Models\CycleItem;
use App\Models\Rack;
use App\Models\ReceiveLog;
use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function pageProps($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props'];
    }

    public function test_operator_performance_aggregates_shopping_qty_per_user_today(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $shippedA = Shopping::factory()->create(['status' => 'shipped', 'shipped_by' => $alice->id, 'shipped_at' => now()]);
        ShoppingItem::factory()->create(['shopping_id' => $shippedA->id, 'quantity' => 12]);

        $shippedB = Shopping::factory()->create(['status' => 'shipped', 'shipped_by' => $bob->id, 'shipped_at' => now()]);
        ShoppingItem::factory()->create(['shopping_id' => $shippedB->id, 'quantity' => 7]);

        // Draft — tidak dihitung
        $draft = Shopping::factory()->create(['status' => 'draft', 'shipped_by' => $alice->id, 'shipped_at' => null]);
        ShoppingItem::factory()->create(['shopping_id' => $draft->id, 'quantity' => 99]);

        // Kemarin — tidak dihitung
        $yesterday = Shopping::factory()->create(['status' => 'shipped', 'shipped_by' => $alice->id, 'shipped_at' => now()->subDay()]);
        ShoppingItem::factory()->create(['shopping_id' => $yesterday->id, 'quantity' => 99]);

        $response = $this->actingAs($alice)->get(route('dashboard'));

        $shopping = $this->pageProps($response)['operatorPerformance']['shopping'];

        $this->assertEqualsCanonicalizing([
            ['name' => 'Alice', 'qty' => 12],
            ['name' => 'Bob', 'qty' => 7],
        ], $shopping);
    }

    public function test_operator_performance_aggregates_receiving_qty_per_user_today(): void
    {
        $rina = User::factory()->create(['name' => 'Rina']);
        $dono = User::factory()->create(['name' => 'Dono']);
        $rack = Rack::factory()->create();
        $cycleItem = CycleItem::factory()->create(['rack_id' => $rack->id]);

        ReceiveLog::create(['cycle_item_id' => $cycleItem->id, 'quantity' => 30, 'rack_id' => $rack->id, 'user_id' => $rina->id]);
        ReceiveLog::create(['cycle_item_id' => $cycleItem->id, 'quantity' => 10, 'rack_id' => $rack->id, 'user_id' => $dono->id]);

        // Kemarin — tidak dihitung
        ReceiveLog::create(['cycle_item_id' => $cycleItem->id, 'quantity' => 99, 'rack_id' => $rack->id, 'user_id' => $rina->id, 'created_at' => now()->subDay()]);

        $response = $this->actingAs($rina)->get(route('dashboard'));

        $receiving = $this->pageProps($response)['operatorPerformance']['receiving'];

        $this->assertEqualsCanonicalizing([
            ['name' => 'Rina', 'qty' => 30],
            ['name' => 'Dono', 'qty' => 10],
        ], $receiving);
    }

    public function test_operator_performance_limits_to_top_10_ordered_by_qty(): void
    {
        $users = collect(range(1, 11))->map(fn ($i) => User::factory()->create(['name' => "Op $i"]));

        $users->each(function ($user, $i) {
            $shopping = Shopping::factory()->create(['status' => 'shipped', 'shipped_by' => $user->id, 'shipped_at' => now()]);
            ShoppingItem::factory()->create(['shopping_id' => $shopping->id, 'quantity' => $i + 1]);
        });

        $response = $this->actingAs($users->first())->get(route('dashboard'));

        $shopping = $this->pageProps($response)['operatorPerformance']['shopping'];

        $this->assertCount(10, $shopping);
        $this->assertSame('Op 11', $shopping[0]['name']);
        $this->assertSame(11, $shopping[0]['qty']);
        $this->assertSame('Op 2', $shopping[9]['name']);
        $this->assertSame(2, $shopping[9]['qty']);
    }
}
