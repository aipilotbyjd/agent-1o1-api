<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleDriveListFilesNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    public function type(): string
    {
        return 'google_drive.list_files';
    }

    public function name(): string
    {
        return 'Google Drive: List Files';
    }

    public function description(): string
    {
        return 'List files and folders in Google Drive.';
    }

    public function icon(): string
    {
        return 'folder';
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
        return 'https://developers.google.com/drive/api/v3/reference/files/list';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'query' => ['type' => 'string'],
                'page_size' => ['type' => 'integer'],
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
            ->get(self::BASE_URL.'/files', [
                'q' => $config['query'] ?? '',
                'pageSize' => $config['page_size'] ?? 10,
                'fields' => 'files(id,name,mimeType,modifiedTime,size)',
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleDrive list_files failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
