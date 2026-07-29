<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class TransformNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.transform';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Transform;
    }

    public function name(): string
    {
        return 'Transform';
    }

    public function description(): string
    {
        return 'Map templated values into the run context.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'shuffle';
    }

    public function color(): string
    {
        return '#0ea5e9';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['mapping' => ['type' => 'object']]];
    }
}
