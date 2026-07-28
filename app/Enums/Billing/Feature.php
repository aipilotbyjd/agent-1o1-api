<?php

namespace App\Enums\Billing;

enum Feature: string
{
    case GitSync = 'git_sync';
    case CustomNodes = 'custom_nodes';
    case WorkflowApprovals = 'workflow_approvals';
    case AgentEvals = 'agent_evals';
    case PrioritySupport = 'priority_support';
}
