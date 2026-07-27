<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowShare;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowShare>
 */
class WorkflowShareFactory extends Factory
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
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'token' => Str::random(40),
            'allow_clone' => true,
            'view_count' => 0,
        ];
    }
}
