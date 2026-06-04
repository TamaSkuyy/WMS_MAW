<?php

namespace Database\Factories;

use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->regexify('[A-D]-[0-9]{2}'),
            'zone' => $this->faker->randomElement(['Zona A', 'Zona B', 'Zona C', 'Zona D']),
        ];
    }
}
