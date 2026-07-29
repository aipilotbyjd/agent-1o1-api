<?php

namespace App\Services\Workflows\Nodes\Apps\Telegram;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TelegramSendMessageNode extends AppNode
{
    public function type(): string
    {
        return 'telegram.send_message';
    }

    public function name(): string
    {
        return 'Telegram: Send Message';
    }

    public function description(): string
    {
        return 'Send a message to a Telegram chat.';
    }

    public function icon(): string
    {
        return 'send';
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
            'required' => ['credential_id', 'chat_id', 'text'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'chat_id' => ['type' => 'string'],
                'text' => ['type' => 'string'],
                'parse_mode' => ['type' => 'string'],
                'disable_preview' => ['type' => 'boolean'],
                'reply_to_message_id' => ['type' => 'integer'],
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
            ->post("https://api.telegram.org/bot{$token}/sendMessage", array_filter([
                'chat_id' => $config['chat_id'],
                'text' => $config['text'],
                'parse_mode' => $config['parse_mode'] ?? 'HTML',
                'disable_web_page_preview' => $config['disable_preview'] ?? null,
                'reply_to_message_id' => $config['reply_to_message_id'] ?? null,
            ]));

        $body = $response->json() ?? [];
        $ok = ($body['ok'] ?? false) === true;
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Telegram send_message error: '.($body['description'] ?? $response->body()));
        }

        return ['message_id' => $body['result']['message_id'] ?? null, 'sent' => true];
    }
}
