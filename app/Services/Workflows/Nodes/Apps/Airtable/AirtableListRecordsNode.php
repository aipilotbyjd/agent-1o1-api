<?php

namespace App\Services\Workflows\Nodes\Apps\Airtable;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class AirtableListRecordsNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.airtable.com/v0';

    public function type(): string
    {
        return 'airtable.list_records';
    }

    public function name(): string
    {
        return 'Airtable: List Records';
    }

    public function description(): string
    {
        return 'List records from an Airtable table.';
    }

    public function icon(): string
    {
        return 'list';
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
        return 'https://airtable.com/developers/web/api/list-records';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'base_id', 'table'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'base_id' => ['type' => 'string'], 'table' => ['type' => 'string'], 'max_records' => ['type' => 'integer'], 'filter_formula' => ['type' => 'string'], 'view' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->get(self::BASE_URL."/{$config['base_id']}/{$config['table']}", [
            'maxRecords' => $config['max_records'] ?? 100, 'filterByFormula' => $config['filter_formula'] ?? null, 'view' => $config['view'] ?? null,
        ]));

        return $data;
    }
}
