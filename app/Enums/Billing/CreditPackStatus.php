<?php

namespace App\Enums\Billing;

enum CreditPackStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
