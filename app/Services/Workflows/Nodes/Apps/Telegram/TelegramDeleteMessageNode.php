<?php

namespace App\Services\Workflows\Nodes\Apps\Telegram;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramDeleteMessageNode extends AppNode
{
    public function type(): string
    {
        return 'telegram.delete_message';
    }

    public function name(): string
    {
        return 'Telegram: Delete Message';
    }

    public function description(): string
    {
        return 'Delete a message from a chat.';
    }

    public function icon(): string
    {
        return 'trash-2';
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
            'required' => ['credential_id', 'chat_id', 'message_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'chat_id' => ['type' => 'string'],
                'message_id' => ['type' => 'integer'],
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

        $token = $credential->data['bot_token'] ?? $credential->data['api_key'] ?? '';

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/deleteMessage", [
                'chat_id' => $config['chat_id'],
                'message_id' => $config['message_id'],
            ]);

        $body = $response->json() ?? [];
        $ok = ($body['ok'] ?? false) === true;
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Telegram delete_message error: '.($body['description'] ?? $response->body()));
        }

        return ['deleted' => true];
    }
}
