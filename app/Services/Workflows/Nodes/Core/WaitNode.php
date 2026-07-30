<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

/**
 * Pauses the run until an external system calls the step back, or the deadline passes.
 *
 * Unlike {@see DelayNode}, which sleeps for a known duration, a wait step has no idea
 * when it will be resumed — it hands out a one-time callback URL and parks. The engine
 * drives it directly (see WorkflowRunner::startWait), so it is not an ExecutableNode.
 */
class WaitNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.wait';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Wait;
    }

    public function name(): string
    {
        return 'Wait for Callback';
    }

    public function description(): string
    {
        return 'Pause until an external system posts to this step\'s callback URL, or the timeout is reached.';
    }

    public function category(): string
    {
        return 'flow-control';
    }

    public function icon(): string
    {
        return 'pause';
    }

    public function color(): string
    {
        return '#6366f1';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'timeout_minutes' => ['type' => 'integer', 'default' => 1440],
                'continue_on_timeout' => ['type' => 'boolean', 'default' => false],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'object'],
                'timed_out' => ['type' => 'boolean'],
            ],
        ];
    }
}
