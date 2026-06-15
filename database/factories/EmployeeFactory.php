<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nik' => fake()->unique()->numerify('NIK-####-####'),
            'job_position_id' => JobPosition::factory(),
            'work_location_id' => WorkLocation::factory(),
            'department_id' => Department::factory(),
            'user_id' => User::factory(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'status' => fake()->randomElement(['Aktif', 'Nonaktif']),
        ];
    }
}
