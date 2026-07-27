<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\WorkflowBuilderDraftVersion;
use App\Models\Workflows\WorkflowBuilderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderDraftVersion>
 */
class WorkflowBuilderDraftVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => WorkflowBuilderSession::factory(),
            'graph_snapshot' => ['steps' => [], 'edges' => []],
        ];
    }
}
