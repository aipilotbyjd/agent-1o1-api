<?php

namespace Database\Factories;

use App\Models\WorkflowBuilderDraftVersion;
use App\Models\WorkflowBuilderSession;
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
