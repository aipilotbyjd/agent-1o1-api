<?php

namespace App\Services\Workflows\Nodes\Flow;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class RetryNode extends NodeDefinition
{
    public function type(): string
    {
        return 'retry';
    }

    public function name(): string
    {
        return 'Retry';
    }

    public function description(): string
    {
        return 'Retry the previous step on failure with configurable attempts and delay.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'rotate-ccw';
    }

    public function color(): string
    {
        return '#f59e0b';
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
            'max_attempts' => ['type' => 'integer', 'default' => 3],
            'delay_ms' => ['type' => 'integer', 'default' => 1000],
        ]];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'attempt' => ['type' => 'integer'],
            'max_attempts' => ['type' => 'integer'],
            'will_retry' => ['type' => 'boolean'],
        ]];
    }
}
