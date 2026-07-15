<?php

namespace Database\Factories;

use App\Models\DeliverySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverySlotFactory extends Factory
{
    protected $model = DeliverySlot::class;

    public function definition(): array
    {
        $slotNumber = $this->faker->unique()->numberBetween(1, 6);

        return [
            'slot_number' => $slotNumber,
            'time_start' => sprintf('%02d:30:00', 5 + $slotNumber * 2),
            'time_end' => sprintf('%02d:30:00', 7 + $slotNumber * 2),
            'label' => "Slot {$slotNumber}",
        ];
    }
}
