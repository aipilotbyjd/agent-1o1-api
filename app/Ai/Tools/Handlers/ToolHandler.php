<?php

namespace App\Ai\Tools\Handlers;

use App\Models\Tool;

interface ToolHandler
{
    /**
     * Execute the tool with the given arguments and return the textual result.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Tool $tool, array $arguments): string;
}
