<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\WorkflowBuilderSession;
use App\Services\Workflows\Nodes\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class InspectNodeSchemaTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'inspect_node_schema';
    }

    public function description(): Stringable|string
    {
        return 'Get the config and output schema for a node, so you know exactly which fields to set in a step\'s "config". Takes the node "type" from list_available_nodes.';
    }

    /**
     * Keyed on the node type rather than the step type: several nodes share a step type
     * (every connector is a "tool" step), so a step-type lookup would be ambiguous.
     */
    public function handle(Request $request): Stringable|string
    {
        $type = (string) ($request->all()['node_type'] ?? '');
        $registry = app(NodeRegistry::class);

        if (! $registry->has($type)) {
            return "No node found with type [{$type}]. Use list_available_nodes to see valid types.";
        }

        $node = $registry->get($type);

        return json_encode([
            'type' => $node->type(),
            'step_type' => $node->stepType()->value,
            'name' => $node->name(),
            'description' => $node->description(),
            'config_schema' => $node->configSchema(),
            'output_schema' => $node->outputSchema(),
            'credential_type' => $node->credentialType(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'node_type' => $schema->string()
                ->description('The node type to inspect, e.g. "slack.post_message" or "core.condition".')
                ->required(),
        ];
    }
}
