<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowShare;
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
