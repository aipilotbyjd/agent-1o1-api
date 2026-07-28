<?php

namespace Database\Factories\Workspaces;

use App\Models\Workspaces\AiGenerationLog;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGenerationLog>
 */
class AiGenerationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => 'autofix',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'prompt_summary' => fake()->sentence(),
            'tokens_used' => fake()->numberBetween(100, 5000),
        ];
    }
}
