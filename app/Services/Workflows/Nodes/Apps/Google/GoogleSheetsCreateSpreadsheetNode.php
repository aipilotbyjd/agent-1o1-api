<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleSheetsCreateSpreadsheetNode extends AppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.create_spreadsheet';
    }

    public function name(): string
    {
        return 'Google Sheets: Create Spreadsheet';
    }

    public function description(): string
    {
        return 'Create a new Google Spreadsheet.';
    }

    public function icon(): string
    {
        return 'file-plus';
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
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/create';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
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
            ->post(self::BASE_URL.'/spreadsheets', [
                'properties' => ['title' => $config['title'] ?? 'New Spreadsheet'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleSheets create_spreadsheet failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
