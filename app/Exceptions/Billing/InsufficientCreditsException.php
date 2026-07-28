<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class InsufficientCreditsException extends RuntimeException
{
    public function __construct(public readonly int $requested, public readonly int $available)
    {
        parent::__construct("Insufficient credits: requested {$requested}, available {$available}.");
    }
}
