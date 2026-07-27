<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Handlers\HttpToolHandler;
use App\Ai\Tools\Handlers\ToolHandler;
use App\Models\Tool;
use InvalidArgumentException;

class ToolHandlerRegistry
{
    public function for(Tool $tool): ToolHandler
    {
        return match ($tool->type) {
            'http' => app(HttpToolHandler::class),
            default => throw new InvalidArgumentException("Unsupported tool type [{$tool->type}]."),
        };
    }
}
