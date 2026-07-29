<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Facades\Cache as CacheFacade;

class CacheNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'cache.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Cache Utilities';
    }

    public function description(): string
    {
        return 'Get, set, and manage cached values with optional TTL.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'database';
    }

    public function color(): string
    {
        return '#0ea5e9';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation', 'key'],
            'properties' => [
                'operation' => ['type' => 'string'],
                'key' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'by' => ['type' => 'integer'],
                'ttl_seconds' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = $config['operation'] ?? 'default';
        $key = (string) ($config['key'] ?? '');

        return match ($operation) {
            'get' => ['value' => CacheFacade::get($key)],
            'put' => $this->put($key, $config),
            'forget' => ['forgotten' => CacheFacade::forget($key)],
            'has' => ['exists' => CacheFacade::has($key)],
            'increment' => ['value' => CacheFacade::increment($key, (int) ($config['by'] ?? 1))],
            default => throw new \RuntimeException("Cache: unknown operation '{$operation}'"),
        };
    }

    private function put(string $key, array $config): array
    {
        $ttl = (int) ($config['ttl_seconds'] ?? 3600);
        CacheFacade::put($key, $config['value'] ?? null, $ttl);

        return ['stored' => true, 'ttl_seconds' => $ttl];
    }
}
