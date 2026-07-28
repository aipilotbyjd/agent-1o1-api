<?php

namespace Database\Factories\Nodes;

use App\Models\Nodes\Node;
use App\Models\Nodes\PinnedNodeData;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PinnedNodeData>
 */
class PinnedNodeDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'node_id' => Node::factory(),
            'data' => ['sample' => fake()->word()],
        ];
    }
}
