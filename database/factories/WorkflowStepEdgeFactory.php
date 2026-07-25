<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepEdge;
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
