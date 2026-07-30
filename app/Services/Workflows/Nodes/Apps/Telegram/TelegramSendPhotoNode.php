<?php

namespace App\Services\Workflows\Nodes\Apps\Telegram;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramSendPhotoNode extends AppNode
{
    public function type(): string
    {
        return 'telegram.send_photo';
    }

    public function name(): string
    {
        return 'Telegram: Send Photo';
    }

    public function description(): string
    {
        return 'Send a photo to a Telegram chat.';
    }

    public function icon(): string
    {
        return 'image';
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
            'required' => ['credential_id', 'chat_id', 'photo'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'chat_id' => ['type' => 'string'],
                'photo' => ['type' => 'string'],
                'caption' => ['type' => 'string'],
                'parse_mode' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message_id' => ['type' => 'integer'],
                'sent' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $token = $credential->data['bot_token'] ?? $credential->data['api_key'] ?? '';

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/sendPhoto", array_filter([
                'chat_id' => $config['chat_id'],
                'photo' => $config['photo'],
                'caption' => $config['caption'] ?? null,
                'parse_mode' => $config['parse_mode'] ?? 'HTML',
            ]));

        $body = $response->json() ?? [];
        $ok = ($body['ok'] ?? false) === true;
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Telegram send_photo error: '.($body['description'] ?? $response->body()));
        }

        return ['message_id' => $body['result']['message_id'] ?? null, 'sent' => true];
    }
}
