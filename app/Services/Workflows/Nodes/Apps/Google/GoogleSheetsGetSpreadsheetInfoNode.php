<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleSheetsGetSpreadsheetInfoNode extends AppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.get_spreadsheet_info';
    }

    public function name(): string
    {
        return 'Google Sheets: Get Spreadsheet Info';
    }

    public function description(): string
    {
        return 'Get metadata about a spreadsheet.';
    }

    public function icon(): string
    {
        return 'info';
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
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/get';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'spreadsheet_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
            ],
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

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/spreadsheets/{$config['spreadsheet_id']}");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleSheets get_spreadsheet_info failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
