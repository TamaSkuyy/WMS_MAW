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
            'name' => $this->faker->unique()->randomElement([
                'Fortuner VRZ', 'Avanza G', 'Innova Zenix', 'Hilux',
                'CR-V Turbo', 'HR-V SE', 'Civic RS',
                'Xpander Cross', 'Pajero Sport', 'L300',
            ]),
            'brand' => $this->faker->randomElement([
                'Toyota', 'Honda', 'Mitsubishi', 'Daihatsu', 'Suzuki',
            ]),
        ];
    }
}
