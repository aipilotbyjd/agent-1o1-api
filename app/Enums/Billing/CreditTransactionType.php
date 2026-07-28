<?php

namespace App\Enums\Billing;

enum CreditTransactionType: string
{
    case Grant = 'grant';
    case Consume = 'consume';
    case Rollover = 'rollover';
    case Refund = 'refund';
}
