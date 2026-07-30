<?php

namespace App\Services\Workflows\Nodes\Apps\Salesforce;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class SalesforceCreateRecordNode extends HttpAppNode
{
    public function type(): string
    {
        return 'salesforce.create_record';
    }

    public function name(): string
    {
        return 'Salesforce: Create Record';
    }

    public function description(): string
    {
        return 'Create a new Salesforce record.';
    }

    public function icon(): string
    {
        return 'plus-square';
    }

    public function color(): string
    {
        return '#00a1e0';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'object', 'fields'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'object' => ['type' => 'string'], 'fields' => ['type' => 'object']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $instanceUrl = $credential->data['instance_url'] ?? '';

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->post("{$instanceUrl}/services/data/v59.0/sobjects/{$config['object']}", $config['fields'] ?? []));

        return $data;
    }
}
