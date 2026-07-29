<?php

namespace App\Services\Workflows\Nodes\Apps\Redis;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Redis as RedisFacade;

class RedisSetNode extends AppNode
{
    public function type(): string
    {
        return 'redis.set';
    }

    public function name(): string
    {
        return 'Redis: Set Value';
    }

    public function description(): string
    {
        return 'Set a value in Redis with optional TTL.';
    }

    public function icon(): string
    {
        return 'database';
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
            'required' => ['credential_id', 'key', 'value'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'key' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'ttl_seconds' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'key' => ['type' => 'string'],
                'stored' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $connection = $this->connection($credential);
        $key = $config['key'];
        $value = is_array($config['value'] ?? null)
            ? json_encode($config['value'])
            : (string) ($config['value'] ?? '');

        if (isset($config['ttl_seconds'])) {
            $connection->setex($key, (int) $config['ttl_seconds'], $value);
        } else {
            $connection->set($key, $value);
        }

        $this->recordMetric($run, true, $startedAt);

        return ['key' => $key, 'stored' => true];
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
