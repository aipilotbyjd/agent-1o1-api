<?php

namespace App\Enums\Workflows;

enum WorkflowStepType: string
{
    case Agent = 'agent';
    case Tool = 'tool';
    case Condition = 'condition';
    case Transform = 'transform';
    case Delay = 'delay';
    case HumanApproval = 'human_approval';
    case Merge = 'merge';
    case SubWorkflow = 'sub_workflow';
    case Loop = 'loop';
}
