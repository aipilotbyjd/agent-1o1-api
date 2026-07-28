<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
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
            'key' => fake()->unique()->word().'_step',
            'type' => 'transform',
            'config' => ['mapping' => ['value' => 'static']],
        ];
    }
}
