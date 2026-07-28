<?php

namespace Database\Factories\Runs;

use App\Models\Runs\ConnectorMetric;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectorMetric>
 */
class ConnectorMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(1, 100);
        $failed = fake()->numberBetween(0, $total);

        return [
            'workspace_id' => Workspace::factory(),
            'connector' => fake()->randomElement(['slack', 'github', 'stripe', 'http']),
            'date' => now()->toDateString(),
            'total_calls' => $total,
            'success_calls' => $total - $failed,
            'failed_calls' => $failed,
            'total_duration_ms' => fake()->numberBetween(100, 60000),
        ];
    }
}
