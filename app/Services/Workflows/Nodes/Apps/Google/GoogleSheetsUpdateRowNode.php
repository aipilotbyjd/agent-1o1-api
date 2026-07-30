<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GoogleSheetsUpdateRowNode extends HttpAppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.update_row';
    }

    public function name(): string
    {
        return 'Google Sheets: Update Row';
    }

    public function description(): string
    {
        return 'Update values in a specific range.';
    }

    public function icon(): string
    {
        return 'edit';
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
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets.values/update';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'spreadsheet_id', 'range', 'values'],
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

        $values = $config['values'];

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->put(self::BASE_URL."/spreadsheets/{$config['spreadsheet_id']}/values/{$config['range']}?valueInputOption=USER_ENTERED", [
                'values' => is_array($values[0] ?? null) ? $values : [$values],
            ]));

        return $data;
    }
}
