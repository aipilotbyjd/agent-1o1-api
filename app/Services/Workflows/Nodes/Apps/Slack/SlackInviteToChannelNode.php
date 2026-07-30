<?php

namespace App\Services\Workflows\Nodes\Apps\Slack;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackInviteToChannelNode extends AppNode
{
    private const BASE_URL = 'https://slack.com/api';

    public function type(): string
    {
        return 'slack.invite_to_channel';
    }

    public function name(): string
    {
        return 'Slack: Invite to Channel';
    }

    public function description(): string
    {
        return 'Invite users to a Slack channel.';
    }

    public function icon(): string
    {
        return 'user-plus';
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
        return 'https://api.slack.com/methods/conversations.invite';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'channel', 'users'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'channel' => ['type' => 'string'],
                'users' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'invited' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL.'/conversations.invite', [
                'channel' => $config['channel'],
                'users' => $config['users'],
            ]);

        $body = $response->json() ?? [];
        $ok = $response->successful() && ($body['ok'] ?? false) === true;

        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Slack invite_to_channel error: '.($body['error'] ?? 'unknown'));
        }

        return ['invited' => true];
    }
}
