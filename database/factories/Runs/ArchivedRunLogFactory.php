<?php

namespace Database\Factories\Runs;

use App\Models\Runs\ArchivedRunLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchivedRunLog>
 */
class ArchivedRunLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => $this->faker->numberBetween(1, 1000),
            'workspace_id' => $this->faker->numberBetween(1, 1000),
            'level' => 'info',
            'message' => $this->faker->sentence(),
            'logged_at' => now()->subDays(30),
            'archived_at' => now(),
        ];
    }
}
