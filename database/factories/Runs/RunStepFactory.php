<?php

namespace Database\Factories\Runs;

use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunStep>
 */
class RunStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'key' => fake()->unique()->slug(2),
            'type' => 'agent',
            'status' => RunStepStatus::Pending,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStepStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStepStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'output' => ['result' => fake()->sentence()],
            'usage' => ['input_tokens' => fake()->numberBetween(10, 500), 'output_tokens' => fake()->numberBetween(10, 500)],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStepStatus::Failed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error' => fake()->sentence(),
        ]);
    }
}
