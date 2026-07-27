<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Nodes\Node;
use App\Models\Workflows\WorkflowBuilderSession;
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
        return 'Get the config/input/output JSON schema for a node step type, so you know which fields to set in a step\'s "config" when adding or updating it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $stepType = (string) ($request->all()['step_type'] ?? '');

        $node = Node::query()
            ->where('step_type', $stepType)
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $this->session->workspace_id))
            ->first();

        if ($node === null) {
            return "No node found for step type [{$stepType}]. Use list_available_nodes to see valid types.";
        }

        return json_encode([
            'type' => $node->step_type->value,
            'name' => $node->name,
            'config_schema' => $node->config_schema,
            'input_schema' => $node->input_schema,
            'output_schema' => $node->output_schema,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'step_type' => $schema->string()->description('The step type to inspect, e.g. "tool" or "condition".')->required(),
        ];
    }
}
