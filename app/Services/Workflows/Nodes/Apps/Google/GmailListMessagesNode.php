<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GmailListMessagesNode extends AppNode
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function type(): string
    {
        return 'gmail.list_messages';
    }

    public function name(): string
    {
        return 'Gmail: List Messages';
    }

    public function description(): string
    {
        return 'List messages in the inbox matching a query.';
    }

    public function icon(): string
    {
        return 'inbox';
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
        return 'https://developers.google.com/gmail/api/reference/rest/v1/users.messages/list';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'query' => ['type' => 'string'],
                'max_results' => ['type' => 'integer'],
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
            ->get(self::BASE_URL.'/users/me/messages', [
                'q' => $config['query'] ?? '',
                'maxResults' => $config['max_results'] ?? 10,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Gmail list_messages failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
