<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test index displays all products
     */
    public function test_index_displays_products(): void
    {
        $products = Product::factory(3)->create();

        $response = $this->actingAs($this->user)->get(route('products.index'));

        $response->assertStatus(200);
        foreach ($products as $product) {
            $response->assertSee($product->part_number);
            $response->assertSee($product->name);
        }
    }

    /**
     * Test index can filter by category
     */
    public function test_index_filters_by_category(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $otherProduct = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['category_id' => $category->id]));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
        $response->assertDontSee($otherProduct->part_number);
    }

    /**
     * Test index can filter by supplier
     */
    public function test_index_filters_by_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $otherProduct = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['supplier_id' => $supplier->id]));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
        $response->assertDontSee($otherProduct->part_number);
    }

    /**
     * Test create shows the form with dropdown data
     */
    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('products.create'));

        $response->assertStatus(200);
    }

    /**
     * Test store creates a new product
     */
    public function test_store_creates_product(): void
    {
        $vehicleModel = VehicleModel::factory()->create();
        $supplier = Supplier::factory()->create();
        $category = ProductCategory::factory()->create();

        $data = [
            'part_number' => 'P5188-0KA03',
            'name' => 'Grade Emblem (VRZ)',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'unit' => 'pcs',
            'base_price' => 150000,
            'description' => 'Original Toyota part',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->post(route('products.store'), $data);

        $this->assertDatabaseHas('products', [
            'part_number' => 'P5188-0KA03',
            'name' => 'Grade Emblem (VRZ)',
        ]);

        $response->assertRedirect(route('products.index'));
    }

    /**
     * Test show displays product details
     */
    public function test_show_displays_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->get(route('products.show', $product));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
        $response->assertSee($product->name);
    }

    /**
     * Test edit shows the edit form
     */
    public function test_edit_shows_form(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->get(route('products.edit', $product));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
    }

    /**
     * Test update modifies product
     */
    public function test_update_modifies_product(): void
    {
        $product = Product::factory()->create(['part_number' => 'OLD-001']);
        $vehicleModel = VehicleModel::factory()->create();
        $supplier = Supplier::factory()->create();
        $category = ProductCategory::factory()->create();

        $updateData = [
            'part_number' => 'NEW-002',
            'name' => 'Updated Part Name',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'unit' => 'set',
            'base_price' => 250000,
            'description' => 'Updated description',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $updateData);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'part_number' => 'NEW-002',
            'name' => 'Updated Part Name',
        ]);

        $response->assertRedirect(route('products.show', $product));
    }

    /**
     * Test destroy deletes product
     */
    public function test_destroy_deletes_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('products.destroy', $product));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $response->assertRedirect(route('products.index'));
    }

    /**
     * Test validation for duplicate part_number
     */
    public function test_store_rejects_duplicate_part_number(): void
    {
        $existing = Product::factory()->create(['part_number' => 'DUP-001']);

        $data = [
            'part_number' => 'DUP-001',
            'name' => 'New Product',
            'vehicle_model_id' => VehicleModel::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'category_id' => ProductCategory::factory()->create()->id,
            'unit' => 'pcs',
        ];

        $response = $this->actingAs($this->user)->post(route('products.store'), $data);

        $response->assertSessionHasErrors('part_number');
        $this->assertDatabaseCount('products', 1);
    }

    /**
     * Test validation for required fields
     */
    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), []);

        $response->assertSessionHasErrors([
            'part_number', 'name', 'vehicle_model_id',
            'supplier_id', 'category_id', 'unit',
        ]);
    }
}
