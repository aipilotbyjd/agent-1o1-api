<?php

namespace App\Services\Workflows\Nodes\Apps\Salesforce;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class SalesforceCreateRecordNode extends AppNode
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
        $startedAt = microtime(true);
        $instanceUrl = $credential->data['instance_url'] ?? '';
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post("{$instanceUrl}/services/data/v59.0/sobjects/{$config['object']}", $config['fields'] ?? []);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Salesforce create_record failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
