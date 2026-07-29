<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GmailDeleteMessageNode extends AppNode
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function type(): string
    {
        return 'gmail.delete_message';
    }

    public function name(): string
    {
        return 'Gmail: Delete Message';
    }

    public function description(): string
    {
        return 'Move a message to trash.';
    }

    public function icon(): string
    {
        return 'trash-2';
    }

    public function color(): string
    {
        return '#ea4335';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.google.com/gmail/api/reference/rest/v1/users.messages/trash';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'message_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'message_id' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'trashed' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL."/users/me/messages/{$config['message_id']}/trash");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Gmail delete_message failed: '.$response->body());
        }

        return ['trashed' => true, 'message_id' => $config['message_id']];
    }
}
