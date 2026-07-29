<?php

namespace App\Services\Workflows\Nodes\Apps\Salesforce;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SalesforceQueryNode extends AppNode
{
    public function type(): string
    {
        return 'salesforce.query';
    }

    public function name(): string
    {
        return 'Salesforce: Query (SOQL)';
    }

    public function description(): string
    {
        return 'Execute a SOQL query against Salesforce.';
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
        return ['type' => 'object', 'properties' => ['soql' => ['type' => 'string']], 'required' => ['soql']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['totalSize' => ['type' => 'integer'], 'records' => ['type' => 'array']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $response = $this->sfHttp($credential)->get('/query', ['q' => $config['soql'] ?? '']);
            if (! $response->successful()) {
                throw new \RuntimeException('Salesforce query failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return $response->json();
        } catch (\Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }

    private function sfHttp(Credential $credential): PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($credential->data['access_token'] ?? '')->baseUrl(($credential->data['instance_url'] ?? '').'/services/data/v59.0');
    }
}
