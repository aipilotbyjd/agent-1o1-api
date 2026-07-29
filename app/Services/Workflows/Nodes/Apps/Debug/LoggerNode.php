<?php

namespace App\Services\Workflows\Nodes\Apps\Debug;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Facades\Log;

class LoggerNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'logger';
    }

    public function name(): string
    {
        return 'Logger';
    }

    public function description(): string
    {
        return 'Log a message at the specified level for debugging.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'terminal';
    }

    public function color(): string
    {
        return '#64748b';
    }

    public function credentialType(): ?string
    {
        return null;
    }

    public function docsUrl(): ?string
    {
        return null;
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'level' => ['type' => 'string', 'enum' => ['debug', 'info', 'warning', 'error'], 'default' => 'info'],
            'message' => ['type' => 'string'],
            'data' => ['type' => 'object', 'default' => null],
        ], 'required' => ['message']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'logged' => ['type' => 'boolean'],
            'level' => ['type' => 'string'],
            'message' => ['type' => 'string'],
        ]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $level = $config['level'] ?? 'info';
        $message = $config['message'] ?? '';

        match ($level) {
            'debug' => Log::debug($message, ['run_id' => $run->id, 'data' => $config['data'] ?? []]),
            'warning' => Log::warning($message, ['run_id' => $run->id, 'data' => $config['data'] ?? []]),
            'error' => Log::error($message, ['run_id' => $run->id, 'data' => $config['data'] ?? []]),
            default => Log::info($message, ['run_id' => $run->id, 'data' => $config['data'] ?? []]),
        };

        return ['logged' => true, 'level' => $level, 'message' => $message];
    }
}
