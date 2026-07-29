<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleDriveCreateFolderNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    public function type(): string
    {
        return 'google_drive.create_folder';
    }

    public function name(): string
    {
        return 'Google Drive: Create Folder';
    }

    public function description(): string
    {
        return 'Create a new folder in Google Drive.';
    }

    public function icon(): string
    {
        return 'folder-plus';
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
        return 'https://developers.google.com/drive/api/v3/reference/files/create';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'name'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'parent_id' => ['type' => 'string'],
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
            ->post(self::BASE_URL.'/files', [
                'name' => $config['name'],
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => isset($config['parent_id']) ? [$config['parent_id']] : [],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleDrive create_folder failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
