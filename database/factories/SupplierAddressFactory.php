<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierAddress>
 */
class SupplierAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => $this->faker->country(),
            'address_type' => 'primary',
        ];
    }
}
