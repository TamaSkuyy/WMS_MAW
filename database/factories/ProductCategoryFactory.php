<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Body Parts', 'Engine Components', 'Electrical',
                'Interior', 'Suspension & Brakes', 'Exhaust System',
                'Cooling System', 'Transmission',
            ]),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
