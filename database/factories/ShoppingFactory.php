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
            'partner_name' => $this->faker->company(),
            'shopping_date' => $this->faker->date(),
            'status' => 'draft',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
