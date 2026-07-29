<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class SubWorkflowNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.sub_workflow';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::SubWorkflow;
    }

    public function name(): string
    {
        return 'Sub-workflow';
    }

    public function description(): string
    {
        return 'Run another workflow and wait for it to finish.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'workflow';
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
            'required' => ['workflow_id'],
            'properties' => [
                'workflow_id' => ['type' => 'integer'],
                'input' => ['type' => 'object'],
            ],
        ];
    }
}
