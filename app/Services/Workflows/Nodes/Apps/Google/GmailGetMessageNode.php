<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GmailGetMessageNode extends AppNode
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function type(): string
    {
        return 'gmail.get_message';
    }

    public function name(): string
    {
        return 'Gmail: Get Message';
    }

    public function description(): string
    {
        return 'Retrieve a specific email message by ID.';
    }

    public function icon(): string
    {
        return 'file-text';
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
        return 'https://developers.google.com/gmail/api/reference/rest/v1/users.messages/get';
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
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/users/me/messages/{$config['message_id']}");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Gmail get_message failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
