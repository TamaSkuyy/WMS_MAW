<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Shift ' . fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Aktif',
        ];
    }
}
