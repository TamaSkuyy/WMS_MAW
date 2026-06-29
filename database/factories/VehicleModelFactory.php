<?php

namespace Database\Factories;

use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleModel>
 */
class VehicleModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => $this->faker->unique()->word(),
            'brand'  => $this->faker->randomElement(['Toyota', 'Honda', 'Mitsubishi', 'Daihatsu']),
            'suffix' => $this->faker->optional(0.6)->randomElement(['VRZ', 'SRZ', 'TRD', 'GR Sport', 'G', 'Cross']),
        ];
    }
}
