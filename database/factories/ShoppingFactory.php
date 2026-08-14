<?php

namespace Database\Factories;

use App\Models\Shopping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shopping>
 */
class ShoppingFactory extends Factory
{
    protected $model = Shopping::class;

    public function definition(): array
    {
        return [
            'shopping_location_id' => null,
            'shopping_date' => $this->faker->date(),
            'status' => 'draft',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
