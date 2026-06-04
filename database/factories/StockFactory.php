<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rack;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'rack_id' => Rack::factory(),
            'quantity' => $this->faker->numberBetween(0, 200),
        ];
    }
}
