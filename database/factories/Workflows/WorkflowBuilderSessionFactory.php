<?php

namespace Database\Factories\Workflows;

use App\Models\User;
use App\Models\Workflows\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderSession>
 */
class WorkflowBuilderSessionFactory extends Factory
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
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'draft_graph' => ['steps' => [], 'edges' => []],
            'status' => 'active',
            'last_activity_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => 'completed']);
    }
}
