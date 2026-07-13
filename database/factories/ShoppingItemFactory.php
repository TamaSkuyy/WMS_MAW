<?php

namespace Database\Factories;

use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\Product;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingItem>
 */
class ShoppingItemFactory extends Factory
{
    protected $model = ShoppingItem::class;

    public function definition(): array
    {
        return [
            'shopping_id' => Shopping::factory(),
            'product_id' => Product::factory(),
            'rack_id' => Rack::factory(),
            'quantity' => $this->faker->numberBetween(1, 50),
        ];
    }
}
