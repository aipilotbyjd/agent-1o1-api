<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\WorkflowBuilderSession;
use App\Services\Workflows\Nodes\NodeDefinition;
use App\Services\Workflows\Nodes\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAvailableNodesTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'list_available_nodes';
    }

    public function description(): Stringable|string
    {
        return 'List every node you can build steps from. Each entry gives the node "type" to inspect, the step "type" to use when adding it, and whether it needs a credential.';
    }

    public function handle(Request $request): Stringable|string
    {
        $nodes = collect(app(NodeRegistry::class)->all())
            ->map(fn (NodeDefinition $node): array => [
                'node_type' => $node->type(),
                'step_type' => $node->stepType()->value,
                'name' => $node->name(),
                'description' => $node->description(),
                'category' => $node->category(),
                'requires_credential' => $node->credentialType(),
            ])
            ->values();

        return json_encode($nodes, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
