<?php

namespace App\Services\Workflows\Nodes\Apps\Redis;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Redis as RedisFacade;

class RedisDeleteNode extends AppNode
{
    public function type(): string
    {
        return 'redis.delete';
    }

    public function name(): string
    {
        return 'Redis: Delete Key';
    }

    public function description(): string
    {
        return 'Delete a key from Redis.';
    }

    public function icon(): string
    {
        return 'trash-2';
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
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deleted' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $connection = $this->connection($credential);
        $deleted = $connection->del($config['key']);

        $this->recordMetric($run, true, $startedAt);

        return ['deleted' => $deleted > 0];
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
