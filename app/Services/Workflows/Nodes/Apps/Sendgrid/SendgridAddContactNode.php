<?php

namespace App\Services\Workflows\Nodes\Apps\Sendgrid;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendgridAddContactNode extends AppNode
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    public function type(): string
    {
        return 'sendgrid.add_contact';
    }

    public function name(): string
    {
        return 'Sendgrid: Add Contact';
    }

    public function description(): string
    {
        return 'Add or update a contact in your Sendgrid list.';
    }

    public function icon(): string
    {
        return 'user-plus';
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
        return 'https://docs.sendgrid.com/api-reference/contacts/add-or-update-a-contact';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'email'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'email' => ['type' => 'string'],
                'first_name' => ['type' => 'string'],
                'last_name' => ['type' => 'string'],
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
            ->put(self::BASE_URL.'/marketing/contacts', [
                'contacts' => [array_filter([
                    'email' => $config['email'],
                    'first_name' => $config['first_name'] ?? null,
                    'last_name' => $config['last_name'] ?? null,
                ])],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Sendgrid add_contact failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
