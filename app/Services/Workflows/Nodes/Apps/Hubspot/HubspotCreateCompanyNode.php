<?php

namespace App\Services\Workflows\Nodes\Apps\Hubspot;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class HubspotCreateCompanyNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.hubapi.com';

    public function type(): string
    {
        return 'hubspot.create_company';
    }

    public function name(): string
    {
        return 'HubSpot: Create Company';
    }

    public function description(): string
    {
        return 'Create a new company in HubSpot.';
    }

    public function icon(): string
    {
        return 'building';
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
        return 'https://developers.hubspot.com/docs/api/crm/companies';
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->post(self::BASE_URL.'/crm/v3/objects/companies', ['properties' => $config['properties'] ?? []]));

        return $data;
    }
}
