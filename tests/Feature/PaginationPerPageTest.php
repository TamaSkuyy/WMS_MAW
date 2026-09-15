<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Navigasi tabel: pilihan baris per halaman (per_page) berlaku di semua index
 * lewat trait HasPagination.
 */
class PaginationPerPageTest extends TestCase
{
    use RefreshDatabase;

    private function pageProps($response): array
    {
        return json_decode(json_encode($response->viewData('page')), true)['props'];
    }

    private function actingUserWith(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate($permission));

        return $user;
    }

    public function test_per_page_query_is_applied(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->actingAs($this->actingUserWith('view products'))
            ->get(route('products.index', ['per_page' => 25]));

        $response->assertOk();
        $this->assertSame(25, $this->pageProps($response)['products']['per_page']);
    }

    public function test_invalid_per_page_falls_back_to_controller_default(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->actingAs($this->actingUserWith('view products'))
            ->get(route('products.index', ['per_page' => 999]));

        $response->assertOk();
        // ProductController default = 15
        $this->assertSame(15, $this->pageProps($response)['products']['per_page']);
    }

    public function test_master_index_default_is_10_when_not_specified(): void
    {
        $supplier = \App\Models\Supplier::factory()->create();

        $response = $this->actingAs($this->actingUserWith('view suppliers'))
            ->get(route('suppliers.index'));

        $response->assertOk();
        $this->assertSame(10, $this->pageProps($response)['suppliers']['per_page']);
    }
}
