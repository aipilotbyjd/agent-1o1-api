<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveDownloadFileNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    public function type(): string
    {
        return 'google_drive.download_file';
    }

    public function name(): string
    {
        return 'Google Drive: Download File';
    }

    public function description(): string
    {
        return 'Download the content of a file.';
    }

    public function icon(): string
    {
        return 'download';
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
        return 'https://developers.google.com/drive/api/v3/reference/files/get#alt=media';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'file_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'file_id' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => ['type' => 'string'],
                'file_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/files/{$config['file_id']}?alt=media");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GoogleDrive download_file failed: '.$response->body());
        }

        return ['content' => $response->body(), 'file_id' => $config['file_id']];
    }
}
