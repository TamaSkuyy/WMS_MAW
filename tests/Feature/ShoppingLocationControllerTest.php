<?php

namespace Tests\Feature;

use App\Models\ShoppingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShoppingLocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_store_saves_barcode(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('create shopping locations'));

        $response = $this->actingAs($this->user)->post(route('shopping-locations.store'), [
            'name' => 'Line A',
            'barcode' => 'LN-A-001',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_locations', ['name' => 'Line A', 'barcode' => 'LN-A-001']);
    }

    public function test_store_accepts_empty_barcode(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('create shopping locations'));

        $response = $this->actingAs($this->user)->post(route('shopping-locations.store'), [
            'name' => 'Line B',
            'barcode' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_locations', ['name' => 'Line B', 'barcode' => null]);
    }

    public function test_store_rejects_duplicate_barcode(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('create shopping locations'));
        ShoppingLocation::create(['name' => 'Line A', 'barcode' => 'LN-A-001']);

        $response = $this->actingAs($this->user)->post(route('shopping-locations.store'), [
            'name' => 'Line B',
            'barcode' => 'LN-A-001',
        ]);

        $response->assertSessionHasErrors('barcode');
    }

    public function test_update_keeps_own_barcode(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('edit shopping locations'));
        $location = ShoppingLocation::create(['name' => 'Line A', 'barcode' => 'LN-A-001']);

        $response = $this->actingAs($this->user)->put(route('shopping-locations.update', $location), [
            'name' => 'Line A2',
            'barcode' => 'LN-A-001',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shopping_locations', ['id' => $location->id, 'name' => 'Line A2', 'barcode' => 'LN-A-001']);
    }
}
