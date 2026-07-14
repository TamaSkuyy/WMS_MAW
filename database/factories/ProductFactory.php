<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'part_number' => strtoupper($this->faker->unique()->bothify('P####-??###')),
            'name' => $this->faker->words(3, true),
            'vehicle_model_id' => fn () => VehicleModel::inRandomOrder()->value('id') ?? VehicleModel::factory(),
            'supplier_id' => fn () => Supplier::inRandomOrder()->value('id') ?? Supplier::factory(),
            'category_id' => fn () => ProductCategory::inRandomOrder()->value('id') ?? ProductCategory::factory(),
            'unit' => $this->faker->randomElement(['pcs', 'set', 'box', 'unit']),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'default_rack_id' => null,
        ];
    }
}
