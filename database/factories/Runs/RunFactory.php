<?php

namespace Database\Factories\Runs;

use App\Enums\Runs\RunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'status' => RunStatus::Pending,
            'trigger_type' => 'manual',
            'input' => ['message' => fake()->sentence()],
            'triggered_by' => User::factory(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'output' => ['result' => fake()->sentence()],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Failed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Cancelled,
            'finished_at' => now(),
        ]);
    }
}
