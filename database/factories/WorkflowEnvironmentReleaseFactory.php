<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowEnvironmentRelease;
use App\Models\WorkspaceEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowEnvironmentRelease>
 */
class WorkflowEnvironmentReleaseFactory extends Factory
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
            'environment_id' => WorkspaceEnvironment::factory(['workspace_id' => $workflow->workspace_id]),
            'version_id' => $workflow->publishVersion()->id,
            'released_at' => now(),
        ];
    }
}
