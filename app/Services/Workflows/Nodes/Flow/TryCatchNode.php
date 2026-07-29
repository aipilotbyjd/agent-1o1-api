<?php

namespace App\Services\Workflows\Nodes\Flow;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class TryCatchNode extends NodeDefinition
{
    public function type(): string
    {
        return 'try_catch';
    }

    public function name(): string
    {
        return 'Try/Catch';
    }

    public function description(): string
    {
        return 'Branch execution based on whether the previous step failed.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'shield';
    }

    public function color(): string
    {
        return '#10b981';
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
        return ['type' => 'object', 'properties' => [
            'has_error' => ['type' => 'boolean'],
            'error' => ['type' => 'object'],
        ]];
    }
}
