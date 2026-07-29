<?php

namespace App\Services\Workflows\Nodes\Apps\Hubspot;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class HubspotListDealsNode extends AppNode
{
    private const BASE_URL = 'https://api.hubapi.com';

    public function type(): string
    {
        return 'hubspot.list_deals';
    }

    public function name(): string
    {
        return 'HubSpot: List Deals';
    }

    public function description(): string
    {
        return 'List deals from HubSpot.';
    }

    public function icon(): string
    {
        return 'briefcase';
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
        return 'https://developers.hubspot.com/docs/api/crm/deals';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'after' => ['type' => 'string']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->get(self::BASE_URL.'/crm/v3/objects/deals', [
            'limit' => $config['limit'] ?? 10, 'after' => $config['after'] ?? null,
        ]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('HubSpot list_deals failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
