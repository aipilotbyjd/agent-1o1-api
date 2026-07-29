<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class HumanApprovalNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.human_approval';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::HumanApproval;
    }

    public function name(): string
    {
        return 'Human Approval';
    }

    public function description(): string
    {
        return 'Pause and notify workspace admins for a decision.';
    }

    public function category(): string
    {
        return 'human';
    }

    public function icon(): string
    {
        return 'user-check';
    }

    public function color(): string
    {
        return '#ec4899';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]];
    }
}
