<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GoogleDriveUpdateFileNode extends HttpAppNode
{
    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    public function type(): string
    {
        return 'google_drive.update_file';
    }

    public function name(): string
    {
        return 'Google Drive: Update File';
    }

    public function description(): string
    {
        return 'Update a file\'s metadata.';
    }

    public function icon(): string
    {
        return 'edit';
    }

    public function color(): string
    {
        return '#34a853';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.google.com/drive/api/v3/reference/files/update';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'file_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'file_id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->patch(self::BASE_URL."/files/{$config['file_id']}", array_filter([
                'name' => $config['name'] ?? null,
            ])));

        return $data;
    }
}
