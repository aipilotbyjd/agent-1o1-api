<?php

namespace App\Services\Workflows\Nodes\Apps\Sendgrid;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class SendgridListContactsNode extends AppNode
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    public function type(): string
    {
        return 'sendgrid.list_contacts';
    }

    public function name(): string
    {
        return 'Sendgrid: List Contacts';
    }

    public function description(): string
    {
        return 'Search contacts in your Sendgrid list.';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function color(): string
    {
        return '#1a82e2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://docs.sendgrid.com/api-reference/contacts/search-contacts';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
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

        $apiKey = $credential->data['api_key'] ?? $credential->data['token'] ?? '';

        $response = Http::timeout(15)
            ->withToken($apiKey)
            ->post(self::BASE_URL.'/marketing/contacts/search', [
                'query' => $config['query'] ?? 'email IS NOT NULL',
                'page_size' => $config['limit'] ?? 50,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Sendgrid list_contacts failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
