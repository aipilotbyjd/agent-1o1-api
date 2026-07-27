<?php

namespace Database\Factories;

use App\Enums\WorkflowStepType;
use App\Models\Node;
use App\Models\NodeCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => NodeCategory::factory(),
            'workspace_id' => null,
            'step_type' => WorkflowStepType::Tool,
            'type' => Str::slug($name, '_'),
            'version' => 1,
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'icon' => 'bolt',
            'color' => '#6366f1',
            'config_schema' => ['type' => 'object', 'properties' => []],
            'input_schema' => null,
            'output_schema' => null,
            'credential_type' => null,
            'cost_hint_usd' => null,
            'latency_hint_ms' => null,
            'is_active' => true,
            'is_premium' => false,
            'is_custom' => false,
            'docs_url' => null,
        ];
    }

    public function custom(): static
    {
        return $this->state(fn (): array => ['is_custom' => true, 'workspace_id' => Workspace::factory()]);
    }
}
