<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class DelayNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.delay';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Delay;
    }

    public function name(): string
    {
        return 'Delay';
    }

    public function description(): string
    {
        return 'Pause before continuing to the next step.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'clock';
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
        return ['type' => 'object', 'properties' => ['seconds' => ['type' => 'integer']]];
    }
}
