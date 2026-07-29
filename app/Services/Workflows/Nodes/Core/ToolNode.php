<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

/**
 * The generic "call a connector" step. Which connector runs is decided by the step's
 * own `node` config value (or a legacy `tool_id`), resolved through the registry.
 */
class ToolNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.tool';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Tool';
    }

    public function description(): string
    {
        return 'Call a connector or a configured workspace tool.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'wrench';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'node' => ['type' => 'string'],
                'tool_id' => ['type' => 'integer'],
                'arguments' => ['type' => 'object'],
            ],
        ];
    }
}
