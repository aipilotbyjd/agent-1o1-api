<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class MergeNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.merge';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Merge;
    }

    public function name(): string
    {
        return 'Merge';
    }

    public function description(): string
    {
        return 'Wait for every incoming branch, then continue.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'git-merge';
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
        return ['type' => 'object', 'properties' => []];
    }
}
