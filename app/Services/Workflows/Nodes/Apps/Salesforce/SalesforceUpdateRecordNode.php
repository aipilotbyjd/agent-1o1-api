<?php

namespace App\Services\Workflows\Nodes\Apps\Salesforce;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SalesforceUpdateRecordNode extends AppNode
{
    public function type(): string
    {
        return 'salesforce.update_record';
    }

    public function name(): string
    {
        return 'Salesforce: Update Record';
    }

    public function description(): string
    {
        return 'Update a record in Salesforce.';
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function color(): string
    {
        return '#00a1e0';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developer.salesforce.com/docs/apis/rest';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['object' => ['type' => 'string'], 'record_id' => ['type' => 'string'], 'fields' => ['type' => 'object']], 'required' => ['object', 'record_id', 'fields']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['updated' => ['type' => 'boolean']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $response = $this->sfHttp($credential)->patch("/sobjects/{$config['object']}/{$config['record_id']}", $config['fields'] ?? []);
            if (! $response->successful()) {
                throw new RuntimeException('Salesforce update_record failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return ['updated' => true];
        } catch (Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }

    private function sfHttp(Credential $credential): PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($credential->data['access_token'] ?? '')->baseUrl(($credential->data['instance_url'] ?? '').'/services/data/v59.0');
    }
}
