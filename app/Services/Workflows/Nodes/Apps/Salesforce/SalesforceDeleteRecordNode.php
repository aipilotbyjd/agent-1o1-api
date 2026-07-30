<?php

namespace App\Services\Workflows\Nodes\Apps\Salesforce;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class SalesforceDeleteRecordNode extends HttpAppNode
{
    public function type(): string
    {
        return 'salesforce.query';
    }

    public function name(): string
    {
        return 'Salesforce: SOQL Query';
    }

    public function description(): string
    {
        return 'Execute a SOQL query.';
    }

    public function icon(): string
    {
        return 'search';
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
            'type' => 'object', 'required' => ['credential_id', 'soql'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'soql' => ['type' => 'string']],
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->get("{$instanceUrl}/services/data/v59.0/query", ['q' => $config['soql']]));

        return $data;
    }
}
