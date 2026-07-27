<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowApproval>
 */
class WorkflowApprovalFactory extends Factory
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
            'requested_by' => User::factory(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
