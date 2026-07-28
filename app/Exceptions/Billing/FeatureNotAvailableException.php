<?php

namespace App\Exceptions\Billing;

use App\Enums\Billing\Feature;
use RuntimeException;

class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(public readonly Feature $feature)
    {
        parent::__construct("Feature not available on current plan: {$feature->value}.");
    }
}
