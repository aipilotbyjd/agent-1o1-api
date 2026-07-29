<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class ConditionNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.condition';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Condition;
    }

    public function name(): string
    {
        return 'Condition';
    }

    public function description(): string
    {
        return 'Branch based on a comparison.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'split';
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
            'required' => ['field'],
            'properties' => [
                'field' => ['type' => 'string'],
                'operator' => ['type' => 'string'],
                'value' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['result' => ['type' => 'string']]];
    }
}
