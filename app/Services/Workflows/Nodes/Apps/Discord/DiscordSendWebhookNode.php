<?php

namespace App\Services\Workflows\Nodes\Apps\Discord;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordSendWebhookNode extends AppNode
{
    public function type(): string
    {
        return 'discord.send_webhook';
    }

    public function name(): string
    {
        return 'Discord: Send Webhook';
    }

    public function description(): string
    {
        return 'Send a message via Discord webhook URL.';
    }

    public function icon(): string
    {
        return 'webhook';
    }

    public function color(): string
    {
        return '#5865f2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'content'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'content' => ['type' => 'string'],
                'username' => ['type' => 'string'],
                'embeds' => ['type' => 'array'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sent' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $webhookUrl = $credential->data['webhook_url'] ?? '';

        $response = Http::timeout(15)
            ->post($webhookUrl, array_filter([
                'content' => $config['content'],
                'username' => $config['username'] ?? null,
                'embeds' => $config['embeds'] ?? [],
            ]));

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Discord webhook failed: '.$response->body());
        }

        return ['sent' => true];
    }
}
