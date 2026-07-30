<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveUploadFileNode extends AppNode
{
    public function type(): string
    {
        return 'google_drive.upload_file';
    }

    public function name(): string
    {
        return 'Google Drive: Upload File';
    }

    public function description(): string
    {
        return 'Upload a file to Google Drive.';
    }

    public function icon(): string
    {
        return 'upload';
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
            'required' => ['credential_id', 'name', 'content'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'mime_type' => ['type' => 'string'],
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

        $mimeType = $config['mime_type'] ?? 'text/plain';
        $metadata = ['name' => $config['name']];
        if (! empty($config['parent_id'])) {
            $metadata['parents'] = [$config['parent_id']];
        }

        $response = Http::timeout(30)
            ->withToken($credential->data['token'] ?? '')
            ->withBody(
                "--boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".
                json_encode($metadata).
                "\r\n--boundary\r\nContent-Type: {$mimeType}\r\n\r\n".
                $config['content'].
                "\r\n--boundary--",
                'multipart/related; boundary=boundary',
            )
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GoogleDrive upload_file failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
