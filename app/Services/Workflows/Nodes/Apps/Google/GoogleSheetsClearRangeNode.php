<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleSheetsClearRangeNode extends AppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.clear_range';
    }

    public function name(): string
    {
        return 'Google Sheets: Clear Range';
    }

    public function description(): string
    {
        return 'Clear values in a specific range.';
    }

    public function icon(): string
    {
        return 'trash-2';
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
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets.values/clear';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'spreadsheet_id', 'range'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
                'range' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cleared' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL."/spreadsheets/{$config['spreadsheet_id']}/values/{$config['range']}:clear");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleSheets clear_range failed: '.$response->body());
        }

        return ['cleared' => true];
    }
}
