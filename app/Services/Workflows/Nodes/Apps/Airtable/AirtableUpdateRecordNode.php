<?php

namespace App\Services\Workflows\Nodes\Apps\Airtable;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class AirtableUpdateRecordNode extends AppNode
{
    private const BASE_URL = 'https://api.airtable.com/v0';

    public function type(): string
    {
        return 'airtable.update_record';
    }

    public function name(): string
    {
        return 'Airtable: Update Record';
    }

    public function description(): string
    {
        return 'Update an existing Airtable record.';
    }

    public function icon(): string
    {
        return 'edit';
    }

    public function color(): string
    {
        return '#18bfff';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://airtable.com/developers/web/api/update-record';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'base_id', 'table', 'record_id', 'fields'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'base_id' => ['type' => 'string'], 'table' => ['type' => 'string'], 'record_id' => ['type' => 'string'], 'fields' => ['type' => 'object']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->patch(self::BASE_URL."/{$config['base_id']}/{$config['table']}/{$config['record_id']}", ['fields' => $config['fields'] ?? []]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Airtable update_record failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
