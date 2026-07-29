<?php

namespace App\Services\Workflows\Nodes\Apps\Discord;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DiscordSendMessageNode extends AppNode
{
    private const BASE_URL = 'https://discord.com/api/v10';

    public function type(): string
    {
        return 'discord.send_message';
    }

    public function name(): string
    {
        return 'Discord: Send Message';
    }

    public function description(): string
    {
        return 'Send a message to a Discord channel.';
    }

    public function icon(): string
    {
        return 'message-circle';
    }

    public function color(): string
    {
        return '#5865f2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'channel_id', 'content'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'channel_id' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'embeds' => ['type' => 'array'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL."/channels/{$config['channel_id']}/messages", [
                'content' => $config['content'],
                'embeds' => $config['embeds'] ?? [],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Discord send_message failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
