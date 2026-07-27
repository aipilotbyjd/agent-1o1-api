<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Nodes\Node;
use App\Models\Workflows\WorkflowBuilderSession;
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
        return 'List the node types available to build workflow steps with, including each one\'s step "type" value and what it does.';
    }

    public function handle(Request $request): Stringable|string
    {
        $nodes = Node::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $this->session->workspace_id))
            ->get(['step_type', 'name', 'description'])
            ->map(fn (Node $node): array => [
                'type' => $node->step_type->value,
                'name' => $node->name,
                'description' => $node->description,
            ]);

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
