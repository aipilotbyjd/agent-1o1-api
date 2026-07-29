<?php

namespace App\Services\Workflows\Nodes\Apps\Hubspot;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class HubspotCreateContactNode extends AppNode
{
    private const BASE_URL = 'https://api.hubapi.com';

    public function type(): string
    {
        return 'hubspot.create_contact';
    }

    public function name(): string
    {
        return 'HubSpot: Create Contact';
    }

    public function description(): string
    {
        return 'Create a new contact in HubSpot.';
    }

    public function icon(): string
    {
        return 'user-plus';
    }

    public function color(): string
    {
        return '#ff7a59';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.hubspot.com/docs/api/crm/contacts';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'properties'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'properties' => ['type' => 'object']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post(self::BASE_URL.'/crm/v3/objects/contacts', ['properties' => $config['properties'] ?? []]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('HubSpot create_contact failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
