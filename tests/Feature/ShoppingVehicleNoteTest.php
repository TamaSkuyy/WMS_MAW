<?php

namespace Tests\Feature;

use App\Models\Shopping;
use App\Models\ShoppingLocation;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Catatan Model Kendaraan + Suffix pada Shopping: input TEKS OPSIONAL yang
 * disimpan di transaksi (bukan relasi ke master produk).
 */
class ShoppingVehicleNoteTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function location(): ShoppingLocation
    {
        return ShoppingLocation::create(['name' => 'LINE V']);
    }

    public function test_store_saves_optional_vehicle_model_and_suffix(): void
    {
        $user = $this->userWith(['create shoppings']);
        $location = $this->location();

        $this->actingAs($user)->post(route('shoppings.store'), [
            'shopping_location_id' => $location->id,
            'shopping_date' => '2026-09-17 10:00:00',
            'frame_number' => 'FR-V1',
            'vehicle_model_label' => 'Toyota Fortuner',
            'vehicle_suffix' => 'VRZ',
        ])->assertRedirect();

        $this->assertDatabaseHas('shoppings', [
            'frame_number' => 'FR-V1',
            'vehicle_model_label' => 'Toyota Fortuner',
            'vehicle_suffix' => 'VRZ',
        ]);
    }

    public function test_store_works_without_vehicle_note(): void
    {
        $user = $this->userWith(['create shoppings']);
        $location = $this->location();

        $this->actingAs($user)->post(route('shoppings.store'), [
            'shopping_location_id' => $location->id,
            'shopping_date' => '2026-09-17 10:00:00',
            'frame_number' => 'FR-V2',
        ])->assertRedirect();

        $shopping = Shopping::where('frame_number', 'FR-V2')->firstOrFail();

        $this->assertNull($shopping->vehicle_model_label);
        $this->assertNull($shopping->vehicle_suffix);
    }

    public function test_update_can_change_and_clear_vehicle_note(): void
    {
        $user = $this->userWith(['edit shoppings']);
        $location = $this->location();
        $shopping = Shopping::factory()->create([
            'shopping_location_id' => $location->id,
            'status' => 'draft',
            'frame_number' => 'FR-V3',
            'vehicle_model_label' => 'Innova',
            'vehicle_suffix' => 'VNT',
        ]);

        $this->actingAs($user)->put(route('shoppings.update', $shopping), [
            'shopping_location_id' => $location->id,
            'shopping_date' => '2026-09-17 11:00:00',
            'frame_number' => 'FR-V3',
            'vehicle_model_label' => 'Fortuner',
            'vehicle_suffix' => 'TRD',
        ])->assertRedirect();

        $this->assertDatabaseHas('shoppings', [
            'id' => $shopping->id,
            'vehicle_model_label' => 'Fortuner',
            'vehicle_suffix' => 'TRD',
        ]);

        // Dikosongkan operator → kembali null (tetap opsional).
        $this->actingAs($user)->put(route('shoppings.update', $shopping), [
            'shopping_location_id' => $location->id,
            'shopping_date' => '2026-09-17 11:00:00',
            'frame_number' => 'FR-V3',
            'vehicle_model_label' => '',
            'vehicle_suffix' => '',
        ])->assertRedirect();

        $shopping->refresh();
        $this->assertNull($shopping->vehicle_model_label);
        $this->assertNull($shopping->vehicle_suffix);
    }

    public function test_detail_page_exposes_vehicle_note(): void
    {
        $user = $this->userWith(['view shoppings']);
        $shopping = Shopping::factory()->create([
            'status' => 'draft',
            'vehicle_model_label' => 'Pajero Sport',
            'vehicle_suffix' => 'DKR',
        ]);

        $this->actingAs($user)->get(route('shoppings.show', $shopping))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Shopping/Show')
                ->where('shopping.vehicle_model_label', 'Pajero Sport')
                ->where('shopping.vehicle_suffix', 'DKR'));
    }

    /**
     * Combobox Model Kendaraan/Suffix mengambil pilihan dari master Model
     * Kendaraan + nilai yang pernah diinput operator (case-insensitive dedupe),
     * supaya user tidak perlu hafal nama model.
     */
    public function test_create_page_exposes_combobox_options_from_master_and_saved_values(): void
    {
        $user = $this->userWith(['create shoppings']);

        VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Fortuner', 'suffix' => 'VRZ']);
        VehicleModel::factory()->create(['brand' => 'Mitsubishi', 'name' => 'Pajero', 'suffix' => 'DKR']);

        // Nilai yang pernah diinput operator — termasuk yang belum ada di master.
        Shopping::factory()->create(['vehicle_model_label' => 'Suzuki Ertiga', 'vehicle_suffix' => 'GL']);
        // Duplikat (beda huruf besar/kecil) harus ikut ter-dedupe.
        Shopping::factory()->create(['vehicle_model_label' => 'toyota fortuner', 'vehicle_suffix' => 'vrz']);

        $this->actingAs($user)->get(route('shoppings.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transactions/Shopping/Create')
                ->where('vehicleModelOptions', ['Mitsubishi Pajero', 'Suzuki Ertiga', 'Toyota Fortuner'])
                ->where('vehicleSuffixOptions', ['DKR', 'GL', 'VRZ']));
    }

    public function test_vehicle_note_is_length_validated(): void
    {
        $user = $this->userWith(['create shoppings']);
        $location = $this->location();

        $this->actingAs($user)->post(route('shoppings.store'), [
            'shopping_location_id' => $location->id,
            'shopping_date' => '2026-09-17 10:00:00',
            'vehicle_model_label' => str_repeat('A', 101),
            'vehicle_suffix' => str_repeat('B', 51),
        ])->assertSessionHasErrors(['vehicle_model_label', 'vehicle_suffix']);
    }
}
