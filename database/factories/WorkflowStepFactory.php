<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowStep;
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
