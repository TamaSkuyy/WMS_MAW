<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Product;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    protected $model = ShipmentItem::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'product_id' => Product::factory(),
            'rack_id' => Rack::factory(),
            'quantity' => $this->faker->numberBetween(1, 50),
        ];
    }
}
