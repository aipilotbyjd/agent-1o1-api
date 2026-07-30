<?php

namespace App\Services\Workflows\Nodes\Apps\Slack;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackListChannelsNode extends AppNode
{
    private const BASE_URL = 'https://slack.com/api';

    public function type(): string
    {
        return 'slack.list_channels';
    }

    public function name(): string
    {
        return 'Slack: List Channels';
    }

    public function description(): string
    {
        return 'List public channels in the workspace.';
    }

    public function icon(): string
    {
        return 'list';
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
        return 'https://api.slack.com/methods/conversations.list';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'exclude_archived' => ['type' => 'boolean'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channels' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL.'/conversations.list', [
                'limit' => $config['limit'] ?? 100,
                'exclude_archived' => $config['exclude_archived'] ?? true,
            ]);

        $body = $response->json() ?? [];
        $ok = $response->successful() && ($body['ok'] ?? false) === true;

        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Slack list_channels error: '.($body['error'] ?? 'unknown'));
        }

        return ['channels' => $body['channels'] ?? []];
    }
}
