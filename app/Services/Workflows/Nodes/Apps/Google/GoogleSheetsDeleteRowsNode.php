<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GoogleSheetsDeleteRowsNode extends HttpAppNode
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4';

    public function type(): string
    {
        return 'google_sheets.delete_rows';
    }

    public function name(): string
    {
        return 'Google Sheets: Delete Rows';
    }

    public function description(): string
    {
        return 'Delete rows from a spreadsheet.';
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
        return 'https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/request#deletedimensionrequest';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'spreadsheet_id', 'start_index', 'end_index'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
                'sheet_id' => ['type' => 'integer'],
                'start_index' => ['type' => 'integer'],
                'end_index' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deleted' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $sheetId = $config['sheet_id'] ?? 0;

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL."/spreadsheets/{$config['spreadsheet_id']}:batchUpdate", [
                'requests' => [[
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $config['start_index'],
                            'endIndex' => $config['end_index'],
                        ],
                    ],
                ]],
            ]));

        return ['deleted' => true];
    }
}
