<?php

namespace App\Services\Workflows\Nodes\Apps\Redis;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Redis as RedisFacade;

class RedisKeysNode extends AppNode
{
    public function type(): string
    {
        return 'redis.keys';
    }

    public function name(): string
    {
        return 'Redis: Find Keys';
    }

    public function description(): string
    {
        return 'Find keys matching a pattern.';
    }

    public function icon(): string
    {
        return 'search';
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
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'pattern' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keys' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $connection = $this->connection($credential);
        $keys = $connection->keys($config['pattern'] ?? '*');

        $this->recordMetric($run, true, $startedAt);

        return ['keys' => $keys];
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
