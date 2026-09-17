<?php

namespace Tests\Feature;

use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tabel Shopping (Transactions > Shopping) menampilkan & mencari FRAME NUMBER
 * (kolom utama alur 2 langkah), bukan cuma lokasi tujuan.
 */
class ShoppingIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::findOrCreate('view shoppings'));
    }

    /** @return array<int,array> */
    private function rows(array $query = []): array
    {
        $response = $this->actingAs($this->user)->get(route('shoppings.index', $query));
        $response->assertOk();

        return $response->viewData('page')['props']['shoppings']['data'];
    }

    public function test_index_exposes_frame_number_column(): void
    {
        Shopping::factory()->create(['frame_number' => 'FRM-ABC-01', 'status' => 'draft']);

        $rows = $this->rows();

        $this->assertCount(1, $rows);
        $this->assertSame('FRM-ABC-01', $rows[0]['frame_number']);
    }

    public function test_search_matches_frame_number(): void
    {
        Shopping::factory()->create(['frame_number' => 'FRM-ABC-01']);
        Shopping::factory()->create(['frame_number' => 'FRM-XYZ-99']);

        $rows = $this->rows(['search' => 'ABC-01']);

        $this->assertCount(1, $rows);
        $this->assertSame('FRM-ABC-01', $rows[0]['frame_number']);
    }

    public function test_search_still_matches_location_name(): void
    {
        $location = ShoppingLocation::create(['name' => 'Line A']);
        Shopping::factory()->create(['frame_number' => 'FRM-001', 'shopping_location_id' => $location->id]);
        Shopping::factory()->create(['frame_number' => 'FRM-002', 'shopping_location_id' => null]);

        $rows = $this->rows(['search' => 'Line A']);

        $this->assertCount(1, $rows);
        $this->assertSame('FRM-001', $rows[0]['frame_number']);
    }
}
