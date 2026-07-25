<?php

namespace App\Enums;

enum WorkflowStepType: string
{
    case Agent = 'agent';
    case Tool = 'tool';
    case Condition = 'condition';
    case Transform = 'transform';
    case Delay = 'delay';
    case HumanApproval = 'human_approval';
}
