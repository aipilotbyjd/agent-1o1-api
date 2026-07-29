<?php

namespace App\Services\Workflows\Nodes\Flow;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class WaitNode extends NodeDefinition
{
    public function type(): string
    {
        return 'wait';
    }

    public function name(): string
    {
        return 'Wait';
    }

    public function description(): string
    {
        return 'Pause execution until a webhook callback or timeout is reached.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'pause';
    }

    public function color(): string
    {
        return '#6366f1';
    }

    public function credentialType(): ?string
    {
        return null;
    }

    public function docsUrl(): ?string
    {
        return null;
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Flow;
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'timeout_minutes' => ['type' => 'integer', 'default' => 1440],
        ]];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'data' => ['type' => 'object'],
        ]];
    }
}
