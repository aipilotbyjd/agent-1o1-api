<?php

namespace App\Services\Workflows\Nodes\Flow;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class LoopNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.loop';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Loop;
    }

    public function name(): string
    {
        return 'Loop';
    }

    public function description(): string
    {
        return 'Map a template over each item in a list, or fan out a sub-workflow per item.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'repeat';
    }

    public function color(): string
    {
        return '#f59e0b';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['items'],
            'properties' => [
                // "map" applies `mapping` to each item in one step; "foreach" starts a
                // child run of `workflow_id` per item and waits for all of them.
                'mode' => ['type' => 'string'],
                'items' => ['type' => 'string'],
                'mapping' => ['type' => 'object'],
                'workflow_id' => ['type' => 'integer'],
                'input' => ['type' => 'object'],
                'max_concurrent' => ['type' => 'integer'],
                'on_item_error' => ['type' => 'string'],
            ],
        ];
    }
}
