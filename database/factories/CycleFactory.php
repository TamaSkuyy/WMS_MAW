<?php

namespace Database\Factories;

use App\Models\Cycle;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cycle>
 */
class CycleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'cycle_number' => $this->faker->unique()->numberBetween(1, 100),
            'status' => 'draft',
            'received_at' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
