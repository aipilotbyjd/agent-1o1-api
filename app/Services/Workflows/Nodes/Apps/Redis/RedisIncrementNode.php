<?php

namespace App\Services\Workflows\Nodes\Apps\Redis;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Redis as RedisFacade;

class RedisIncrementNode extends AppNode
{
    public function type(): string
    {
        return 'redis.increment';
    }

    public function name(): string
    {
        return 'Redis: Increment';
    }

    public function description(): string
    {
        return 'Atomically increment a Redis counter.';
    }

    public function icon(): string
    {
        return 'trending-up';
    }

    public function color(): string
    {
        return '#dc382d';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'key'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'key' => ['type' => 'string'],
                'by' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $connection = $this->connection($credential);
        $value = $connection->incrby($config['key'], (int) ($config['by'] ?? 1));

        $this->recordMetric($run, true, $startedAt);

        return ['value' => $value];
    }

    private function connection(Credential $credential): mixed
    {
        $data = $credential->data ?? [];
        $name = 'workflow_redis_'.md5(json_encode($data));

        config(["database.redis.{$name}" => [
            'host' => $data['host'] ?? '127.0.0.1',
            'port' => (int) ($data['port'] ?? 6379),
            'password' => $data['password'] ?? null,
            'database' => (int) ($data['database'] ?? 0),
        ]]);

        return RedisFacade::connection($name);
    }
}
