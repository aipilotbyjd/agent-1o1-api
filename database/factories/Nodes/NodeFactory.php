<?php

namespace Database\Factories\Nodes;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Nodes\Node;
use App\Models\Nodes\NodeCategory;
use App\Models\Workspaces\Workspace;
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

    /**
     * A workspace-authored node: it carries its own call definition, so it is
     * executable and can be attached to an agent as a tool.
     */
    public function custom(): static
    {
        return $this->state(fn (): array => [
            'is_custom' => true,
            'workspace_id' => Workspace::factory(),
            'config' => ['url' => 'https://api.example.com/lookup', 'method' => 'GET'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function callingUrl(string $url, string $method = 'GET', array $config = []): static
    {
        return $this->state(fn (): array => [
            'config' => ['url' => $url, 'method' => $method, ...$config],
        ]);
    }

    /**
     * Give the node a config schema whose fields act as its call arguments.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $required
     */
    public function withArguments(array $properties, array $required = []): static
    {
        return $this->state(fn (): array => [
            'config_schema' => array_filter([
                'type' => 'object',
                'properties' => $properties,
                'required' => $required === [] ? null : $required,
            ]),
        ]);
    }
}
