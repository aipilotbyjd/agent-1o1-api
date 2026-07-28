<?php

namespace Database\Factories\Runs;

use App\Models\Runs\Run;
use App\Models\Runs\RunLog;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunLog>
 */
class RunLogFactory extends Factory
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
            'workspace_id' => Workspace::factory(),
            'level' => 'info',
            'message' => $this->faker->sentence(),
            'logged_at' => now(),
        ];
    }
}
