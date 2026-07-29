<?php

namespace App\Services\Workflows\Nodes\Apps\Telegram;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TelegramGetUpdatesNode extends AppNode
{
    public function type(): string
    {
        return 'telegram.get_updates';
    }

    public function name(): string
    {
        return 'Telegram: Get Updates';
    }

    public function description(): string
    {
        return 'Get updates from a Telegram bot.';
    }

    public function icon(): string
    {
        return 'refresh-cw';
    }

    public function color(): string
    {
        return '#26a5e4';
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
                'offset' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
                'timeout' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'updates' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $token = $credential->data['bot_token'] ?? $credential->data['api_key'] ?? '';

        $response = Http::timeout(15)
            ->get("https://api.telegram.org/bot{$token}/getUpdates", array_filter([
                'offset' => $config['offset'] ?? null,
                'limit' => $config['limit'] ?? 10,
                'timeout' => $config['timeout'] ?? 0,
            ]));

        $body = $response->json() ?? [];
        $ok = ($body['ok'] ?? false) === true;
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Telegram get_updates error: '.($body['description'] ?? $response->body()));
        }

        return ['updates' => $body['result'] ?? []];
    }
}
