<?php

namespace App\Exceptions\Workflows;

use RuntimeException;

class UnknownNodeException extends RuntimeException
{
    public function __construct(public readonly string $type, ?string $message = null)
    {
        parent::__construct($message ?? "Unknown node type [{$type}].");
    }
}
