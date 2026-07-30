<?php

namespace App\Services\Workflows\Nodes\Apps\Mongodb;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MongodbFindNode extends AppNode
{
    public function type(): string
    {
        return 'mongodb.find';
    }

    public function name(): string
    {
        return 'MongoDB: Find Documents';
    }

    public function description(): string
    {
        return 'Query documents from a MongoDB collection using the Data API.';
    }

    public function icon(): string
    {
        return 'database';
    }

    public function color(): string
    {
        return '#47a248';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://www.mongodb.com/docs/atlas/app-services/data-api/';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'filter' => ['type' => 'object'], 'limit' => ['type' => 'integer'], 'database' => ['type' => 'string']], 'required' => ['collection']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['documents' => ['type' => 'array']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $payload = array_filter(['dataSource' => $credential->data['data_source'] ?? 'Cluster0', 'database' => $credential->data['database'], 'collection' => $config['collection'], 'filter' => $config['filter'] ?? null, 'limit' => $config['limit'] ?? null]);
            $response = Http::withHeaders(['api-key' => $credential->data['api_key'] ?? ''])->post(rtrim($credential->data['data_api_url'] ?? '', '/').'/action/find', $payload);
            if (! $response->successful()) {
                throw new RuntimeException('MongoDB find failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return $response->json();
        } catch (Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }
}
