<?php

namespace Database\Factories;

use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'partner_name' => $this->faker->company(),
            'shipment_date' => $this->faker->date(),
            'status' => 'draft',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
