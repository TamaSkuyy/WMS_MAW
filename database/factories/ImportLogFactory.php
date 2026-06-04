<?php

namespace Database\Factories;

use App\Services\ImportExport\Enums\ImportStatus;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'model_type' => \App\Models\User::class,
            'file_name' => fake()->word() . '.xlsx',
            'file_format' => 'xlsx',
            'status' => ImportStatus::Pending->value,
            'column_mapping' => ['email' => 'A', 'name' => 'B'],
            'total_rows' => fake()->numberBetween(10, 1000),
            'processed_rows' => 0,
            'skipped_rows' => 0,
            'errors' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Completed->value,
            'processed_rows' => $attributes['total_rows'],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Failed->value,
            'errors' => [['row' => 1, 'field' => 'email', 'message' => 'Invalid email']],
        ]);
    }
}
