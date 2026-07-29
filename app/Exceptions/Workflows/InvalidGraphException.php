<?php

namespace App\Exceptions\Workflows;

use RuntimeException;

class InvalidGraphException extends RuntimeException
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('The workflow graph is invalid: '.implode(' ', $issues));
    }
}
