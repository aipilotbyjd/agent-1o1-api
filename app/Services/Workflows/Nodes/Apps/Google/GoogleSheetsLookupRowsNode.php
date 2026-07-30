<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSheetsLookupRowsNode extends AppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.lookup_rows';
    }

    public function name(): string
    {
        return 'Google Sheets: Lookup Rows';
    }

    public function description(): string
    {
        return 'Find rows matching a value in a specific column.';
    }

    public function icon(): string
    {
        return 'search';
    }

    public function color(): string
    {
        return '#0f9d58';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets.values/get';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'spreadsheet_id', 'lookup_value'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
                'range' => ['type' => 'string'],
                'lookup_column' => ['type' => 'string'],
                'lookup_value' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rows' => ['type' => 'array'],
                'count' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $range = $config['range'] ?? 'Sheet1';
        $lookupColumn = $config['lookup_column'] ?? 'A';
        $lookupValue = $config['lookup_value'];

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/spreadsheets/{$config['spreadsheet_id']}/values/{$range}");

        if (! $response->successful()) {
            $this->recordMetric($run, false, $startedAt);
            throw new RuntimeException('GoogleSheets lookup_rows failed: '.$response->body());
        }

        $data = $response->json() ?? [];
        $rows = $data['values'] ?? [];
        $colIndex = ord(strtoupper($lookupColumn)) - ord('A');

        $matched = array_values(array_filter($rows, fn ($row) => ($row[$colIndex] ?? null) === $lookupValue));

        $this->recordMetric($run, true, $startedAt);

        return ['rows' => $matched, 'count' => count($matched)];
    }
}
