<?php

namespace Database\Factories\Runs;

use App\Models\Runs\RunReplayPack;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunReplayPack>
 */
class RunReplayPackFactory extends Factory
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
            'workflow_id' => Workflow::factory(),
            'label' => $this->faker->sentence(3),
            'version_snapshot' => ['steps' => [], 'edges' => []],
        ];
    }
}
