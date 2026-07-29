<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GmailCreateDraftNode extends AppNode
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function type(): string
    {
        return 'gmail.create_draft';
    }

    public function name(): string
    {
        return 'Gmail: Create Draft';
    }

    public function description(): string
    {
        return 'Create a draft email message.';
    }

    public function icon(): string
    {
        return 'file';
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
        return 'https://developers.google.com/gmail/api/reference/rest/v1/users.drafts/create';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'to', 'subject', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
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

        $raw = base64_encode(
            "To: {$config['to']}\r\n".
            "Subject: {$config['subject']}\r\n\r\n".
            $config['body']
        );

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL.'/users/me/drafts', [
                'message' => ['raw' => strtr($raw, '+/', '-_')],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Gmail create_draft failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
