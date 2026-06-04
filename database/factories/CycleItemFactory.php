<?php

namespace Database\Factories;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CycleItem>
 */
class CycleItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cycle_id' => Cycle::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'received_quantity' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
