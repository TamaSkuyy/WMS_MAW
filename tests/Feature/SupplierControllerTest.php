<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test index displays all suppliers
     */
    public function test_index_displays_suppliers(): void
    {
        $suppliers = Supplier::factory(3)
            ->has(SupplierAddress::factory(), 'addresses')
            ->create();

        $response = $this->actingAs($this->user)->get(route('suppliers.index'));

        $response->assertStatus(200);
        foreach ($suppliers as $supplier) {
            $response->assertSee($supplier->name);
            $response->assertSee($supplier->email);
        }
    }

    /**
     * Test create shows the creation form
     */
    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('suppliers.create'));

        $response->assertStatus(200);
    }

    /**
     * Test store creates a new supplier with address
     */
    public function test_store_creates_supplier(): void
    {
        $data = [
            'name' => 'PT Test Supplier',
            'contact_person' => 'John Doe',
            'email' => 'john@testsupplier.com',
            'phone' => '+62812345678',
            'street' => 'Jl. Test 123',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
        ];

        $response = $this->actingAs($this->user)->post(route('suppliers.store'), $data);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Test Supplier',
            'email' => 'john@testsupplier.com',
        ]);

        $this->assertDatabaseHas('supplier_addresses', [
            'city' => 'Jakarta',
            'address_type' => 'primary',
        ]);

        $response->assertRedirect();
    }

    /**
     * Test show displays supplier details
     */
    public function test_show_displays_supplier(): void
    {
        $supplier = Supplier::factory()
            ->has(SupplierAddress::factory(), 'addresses')
            ->create();

        $response = $this->actingAs($this->user)->get(route('suppliers.show', $supplier));

        $response->assertStatus(200);
        $response->assertSee($supplier->name);
        $response->assertSee($supplier->email);
    }

    /**
     * Test edit shows the edit form
     */
    public function test_edit_shows_form(): void
    {
        $supplier = Supplier::factory()
            ->has(SupplierAddress::factory(), 'addresses')
            ->create();

        $response = $this->actingAs($this->user)->get(route('suppliers.edit', $supplier));

        $response->assertStatus(200);
        $response->assertSee($supplier->name);
    }

    /**
     * Test update modifies supplier and address
     */
    public function test_update_modifies_supplier(): void
    {
        $supplier = Supplier::factory()
            ->has(SupplierAddress::factory(), 'addresses')
            ->create();

        $updateData = [
            'name' => 'Updated Supplier',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@updated.com',
            'phone' => '+62812345679',
            'street' => 'Jl. Updated 456',
            'city' => 'Surabaya',
            'state' => 'Jawa Timur',
            'postal_code' => '60000',
            'country' => 'Indonesia',
        ];

        $response = $this->actingAs($this->user)->put(route('suppliers.update', $supplier), $updateData);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
            'email' => 'jane@updated.com',
        ]);

        $this->assertDatabaseHas('supplier_addresses', [
            'supplier_id' => $supplier->id,
            'city' => 'Surabaya',
        ]);

        $response->assertRedirect();
    }

    /**
     * Test destroy deletes supplier and cascades to addresses
     */
    public function test_destroy_deletes_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        // Create one primary address
        $supplier->addresses()->create([
            'street' => 'Jl. Main 123',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
            'address_type' => 'primary',
        ]);

        // Create one shipping address
        $supplier->addresses()->create([
            'street' => 'Jl. Shipping 456',
            'city' => 'Bandung',
            'state' => 'Jawa Barat',
            'postal_code' => '40000',
            'country' => 'Indonesia',
            'address_type' => 'shipping',
        ]);

        $addressCount = $supplier->addresses()->count();
        $this->assertEquals(2, $addressCount);

        $response = $this->actingAs($this->user)->delete(route('suppliers.destroy', $supplier));

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseMissing('supplier_addresses', ['supplier_id' => $supplier->id]);

        $response->assertRedirect();
    }

    /**
     * Test validation for duplicate email
     */
    public function test_store_rejects_duplicate_email(): void
    {
        $existing = Supplier::factory()->create(['email' => 'test@test.com']);

        $data = [
            'name' => 'New Supplier',
            'email' => 'test@test.com',
            'street' => 'Jl. Test',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
        ];

        $response = $this->actingAs($this->user)->post(route('suppliers.store'), $data);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('suppliers', 1);
    }

    /**
     * Test validation for duplicate name
     */
    public function test_store_rejects_duplicate_name(): void
    {
        $existing = Supplier::factory()->create(['name' => 'Existing Supplier']);

        $data = [
            'name' => 'Existing Supplier',
            'email' => 'new@test.com',
            'street' => 'Jl. Test',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
        ];

        $response = $this->actingAs($this->user)->post(route('suppliers.store'), $data);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('suppliers', 1);
    }

    /**
     * Test validation for required fields
     */
    public function test_store_validates_required_fields(): void
    {
        $data = [];

        $response = $this->actingAs($this->user)->post(route('suppliers.store'), $data);

        $response->assertSessionHasErrors(['name', 'email', 'street', 'city', 'state', 'postal_code']);
    }

    public function test_supplier_gets_an_auto_generated_code_on_creation(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'PT. ADI JAYA MAKMUR']);

        $this->assertSame('AJM', $supplier->code);
    }

    public function test_supplier_name_that_collides_on_initials_gets_a_distinct_code(): void
    {
        Supplier::factory()->create(['name' => 'PT. ADI JAYA MAKMUR']);
        $second = Supplier::factory()->create(['name' => 'PT. AGUNG JAYA MULIA']);

        $this->assertSame('AJM1', $second->code);
    }

    public function test_update_syncs_delivery_slot_schedule(): void
    {
        $supplier = Supplier::factory()->create();
        $supplier->addresses()->create([
            'street' => 'Jl. Test', 'city' => 'Jakarta', 'state' => 'DKI',
            'postal_code' => '12345', 'country' => 'Indonesia', 'address_type' => 'primary',
        ]);
        $slotOne = \App\Models\DeliverySlot::where('slot_number', 1)->first();
        $slotFour = \App\Models\DeliverySlot::where('slot_number', 4)->first();

        $response = $this->actingAs($this->user)->put(route('suppliers.update', $supplier), [
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'street' => 'Jl. Test', 'city' => 'Jakarta', 'state' => 'DKI',
            'postal_code' => '12345', 'country' => 'Indonesia',
            'delivery_slot_ids' => [$slotOne->id, $slotFour->id],
        ]);

        $response->assertRedirect();
        $this->assertEqualsCanonicalizing(
            [1, 4],
            $supplier->fresh()->scheduledSlots()->pluck('slot_number')->all()
        );
    }
}
