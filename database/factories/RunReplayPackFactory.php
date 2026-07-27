<?php

namespace Database\Factories;

use App\Models\RunReplayPack;
use App\Models\Workflow;
use App\Models\Workspace;
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
