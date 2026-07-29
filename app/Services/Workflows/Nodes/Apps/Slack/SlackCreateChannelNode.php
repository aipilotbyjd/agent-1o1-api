<?php

namespace App\Services\Workflows\Nodes\Apps\Slack;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class SlackCreateChannelNode extends AppNode
{
    private const BASE_URL = 'https://slack.com/api';

    public function type(): string
    {
        return 'slack.create_channel';
    }

    public function name(): string
    {
        return 'Slack: Create Channel';
    }

    public function description(): string
    {
        return 'Create a new Slack channel.';
    }

    public function icon(): string
    {
        return 'hash';
    }

    public function color(): string
    {
        return '#4a154b';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://api.slack.com/methods/conversations.create';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'name'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'is_private' => ['type' => 'boolean'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'is_private' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL.'/conversations.create', [
                'name' => $config['name'],
                'is_private' => $config['is_private'] ?? false,
            ]);

        $body = $response->json() ?? [];
        $ok = $response->successful() && ($body['ok'] ?? false) === true;

        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Slack create_channel error: '.($body['error'] ?? 'unknown'));
        }

        return $body['channel'] ?? [];
    }
}
