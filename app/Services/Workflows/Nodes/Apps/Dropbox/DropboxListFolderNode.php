<?php

namespace App\Services\Workflows\Nodes\Apps\Dropbox;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DropboxListFolderNode extends AppNode
{
    private const BASE_URL = 'https://api.dropboxapi.com/2';

    public function type(): string
    {
        return 'dropbox.list_folder';
    }

    public function name(): string
    {
        return 'Dropbox: List Folder';
    }

    public function description(): string
    {
        return 'List contents of a Dropbox folder.';
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function color(): string
    {
        return '#0061ff';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'path' => ['type' => 'string']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post(self::BASE_URL.'/files/list_folder', ['path' => $config['path'] ?? '']);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Dropbox list_folder failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
