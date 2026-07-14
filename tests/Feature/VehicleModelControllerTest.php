<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_vehicle_models(): void
    {
        VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->get(route('vehicle-models.index'));

        $response->assertStatus(200);
        $response->assertSee('Fortuner');
        $response->assertSee('Toyota');
    }

    public function test_store_creates_vehicle_model_with_suffix(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'VRZ',
        ]);

        $this->assertDatabaseHas('vehicle_models', [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'VRZ',
        ]);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_store_creates_vehicle_model_without_suffix(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'  => 'Hilux',
            'brand' => 'Toyota',
        ]);

        $this->assertDatabaseHas('vehicle_models', [
            'name'   => 'Hilux',
            'brand'  => 'Toyota',
            'suffix' => null,
        ]);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_same_name_brand_different_suffix_allowed(): void
    {
        VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'SRZ',
        ]);

        $this->assertDatabaseCount('vehicle_models', 2);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_store_uppercases_suffix(): void
    {
        $response = $this->actingAs($this->user)->post(route('vehicle-models.store'), [
            'name'   => 'Rush',
            'brand'  => 'Toyota',
            'suffix' => 'sd12',
        ]);

        $this->assertDatabaseHas('vehicle_models', [
            'name'   => 'Rush',
            'brand'  => 'Toyota',
            'suffix' => 'SD12',
        ]);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_update_uppercases_suffix(): void
    {
        $model = VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->put(route('vehicle-models.update', $model), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'asRT2a',
        ]);

        $this->assertDatabaseHas('vehicle_models', ['id' => $model->id, 'suffix' => 'ASRT2A']);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_update_modifies_suffix(): void
    {
        $model = VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->put(route('vehicle-models.update', $model), [
            'name'   => 'Fortuner',
            'brand'  => 'Toyota',
            'suffix' => 'TRD',
        ]);

        $this->assertDatabaseHas('vehicle_models', ['id' => $model->id, 'suffix' => 'TRD']);
        $response->assertRedirect(route('vehicle-models.index'));
    }

    public function test_destroy_deletes_vehicle_model(): void
    {
        $model = VehicleModel::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('vehicle-models.destroy', $model));

        $this->assertDatabaseMissing('vehicle_models', ['id' => $model->id]);
        $response->assertRedirect(route('vehicle-models.index'));
    }
}
