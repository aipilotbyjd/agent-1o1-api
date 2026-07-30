<?php

namespace App\Services\Workflows\Nodes\Apps\Hubspot;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class HubspotGetContactNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.hubapi.com';

    public function type(): string
    {
        return 'hubspot.get_contact';
    }

    public function name(): string
    {
        return 'HubSpot: Get Contact';
    }

    public function description(): string
    {
        return 'Get a contact by ID from HubSpot.';
    }

    public function icon(): string
    {
        return 'user';
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
            'type' => 'object', 'required' => ['credential_id', 'object_id'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'object_id' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->get(self::BASE_URL."/crm/v3/objects/contacts/{$config['object_id']}"));

        return $data;
    }
}
