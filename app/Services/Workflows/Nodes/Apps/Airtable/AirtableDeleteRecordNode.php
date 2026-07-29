<?php

namespace App\Services\Workflows\Nodes\Apps\Airtable;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class AirtableDeleteRecordNode extends AppNode
{
    private const BASE_URL = 'https://api.airtable.com/v0';

    public function type(): string
    {
        return 'airtable.delete_record';
    }

    public function name(): string
    {
        return 'Airtable: Delete Record';
    }

    public function description(): string
    {
        return 'Delete a record from an Airtable table.';
    }

    public function icon(): string
    {
        return 'trash-2';
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
        return 'https://airtable.com/developers/web/api/delete-record';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'base_id', 'table', 'record_id'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'base_id' => ['type' => 'string'], 'table' => ['type' => 'string'], 'record_id' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['deleted' => ['type' => 'boolean']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->delete(self::BASE_URL."/{$config['base_id']}/{$config['table']}/{$config['record_id']}");
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Airtable delete_record failed: '.$response->body());
        }

        return ['deleted' => true];
    }
}
