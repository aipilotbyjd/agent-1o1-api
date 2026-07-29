<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleSheetsAppendRowNode extends AppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.append_row';
    }

    public function name(): string
    {
        return 'Google Sheets: Append Row';
    }

    public function description(): string
    {
        return 'Append a row of data to a spreadsheet.';
    }

    public function icon(): string
    {
        return 'plus';
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
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets.values/append';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'spreadsheet_id', 'values'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
                'range' => ['type' => 'string'],
                'values' => ['type' => 'array'],
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

        $range = $config['range'] ?? 'Sheet1';
        $values = $config['values'];

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL."/spreadsheets/{$config['spreadsheet_id']}/values/{$range}:append?valueInputOption=USER_ENTERED", [
                'values' => is_array($values[0] ?? null) ? $values : [$values],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleSheets append_row failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
