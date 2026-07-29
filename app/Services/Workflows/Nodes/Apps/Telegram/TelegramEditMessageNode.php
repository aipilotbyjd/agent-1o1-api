<?php

namespace App\Services\Workflows\Nodes\Apps\Telegram;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TelegramEditMessageNode extends AppNode
{
    public function type(): string
    {
        return 'telegram.edit_message';
    }

    public function name(): string
    {
        return 'Telegram: Edit Message';
    }

    public function description(): string
    {
        return 'Edit a previously sent message.';
    }

    public function icon(): string
    {
        return 'edit';
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
            'required' => ['credential_id', 'chat_id', 'message_id', 'text'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'chat_id' => ['type' => 'string'],
                'message_id' => ['type' => 'integer'],
                'text' => ['type' => 'string'],
                'parse_mode' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'edited' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $token = $credential->data['bot_token'] ?? $credential->data['api_key'] ?? '';

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/editMessageText", array_filter([
                'chat_id' => $config['chat_id'],
                'message_id' => $config['message_id'],
                'text' => $config['text'],
                'parse_mode' => $config['parse_mode'] ?? 'HTML',
            ]));

        $body = $response->json() ?? [];
        $ok = ($body['ok'] ?? false) === true;
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Telegram edit_message error: '.($body['description'] ?? $response->body()));
        }

        return ['edited' => true];
    }
}
