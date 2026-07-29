<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class TriggerNode extends NodeDefinition
{
    public function type(): string
    {
        return 'trigger';
    }

    public function name(): string
    {
        return 'Trigger';
    }

    public function description(): string
    {
        return 'Entry point for workflow triggers. Passes trigger data through to the workflow.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'zap';
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
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }
}
