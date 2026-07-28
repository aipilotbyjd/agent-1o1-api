<?php

namespace App\Exceptions\Billing;

use App\Enums\Billing\Limit;
use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(public readonly Limit $limit)
    {
        parent::__construct("Plan limit exceeded: {$limit->value}.");
    }
}
