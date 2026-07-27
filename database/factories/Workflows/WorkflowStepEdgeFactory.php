<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowStep;
use App\Models\Workflows\WorkflowStepEdge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStepEdge>
 */
class WorkflowStepEdgeFactory extends Factory
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
            'from_step_id' => WorkflowStep::factory(),
            'to_step_id' => WorkflowStep::factory(),
            'condition' => null,
        ];
    }
}
