<?php

namespace App\Services\Workflows\Nodes\Apps\Redis;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Redis as RedisFacade;

class RedisPublishNode extends AppNode
{
    public function type(): string
    {
        return 'redis.publish';
    }

    public function name(): string
    {
        return 'Redis: Publish';
    }

    public function description(): string
    {
        return 'Publish a message to a Redis channel.';
    }

    public function icon(): string
    {
        return 'radio';
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
            'required' => ['credential_id', 'channel', 'message'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'channel' => ['type' => 'string'],
                'message' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'receivers' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $connection = $this->connection($credential);
        $receivers = $connection->publish($config['channel'], $config['message']);

        $this->recordMetric($run, true, $startedAt);

        return ['receivers' => $receivers];
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
