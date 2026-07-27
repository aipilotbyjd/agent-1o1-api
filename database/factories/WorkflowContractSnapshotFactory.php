<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowContractSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowContractSnapshot>
 */
class WorkflowContractSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflow = Workflow::factory()->create();

        return [
            'workspace_id' => $workflow->workspace_id,
            'workflow_id' => $workflow->id,
            'node_signature' => [],
        ];
    }
}
