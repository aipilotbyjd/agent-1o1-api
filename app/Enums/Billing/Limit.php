<?php

namespace App\Enums\Billing;

enum Limit: string
{
    case CreditsMonthly = 'credits_monthly';
    case Workflows = 'workflows';
    case Agents = 'agents';
    case Members = 'members';
    case Environments = 'environments';
}
