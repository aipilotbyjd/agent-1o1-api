<?php

namespace App\Services\Workflows\Nodes\Apps\Dropbox;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DropboxCreateFolderNode extends AppNode
{
    private const BASE_URL = 'https://api.dropboxapi.com/2';

    public function type(): string
    {
        return 'dropbox.create_folder';
    }

    public function name(): string
    {
        return 'Dropbox: Create Folder';
    }

    public function description(): string
    {
        return 'Create a new folder in Dropbox.';
    }

    public function icon(): string
    {
        return 'folder-plus';
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
            'type' => 'object', 'required' => ['credential_id', 'path'],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post(self::BASE_URL.'/files/create_folder_v2', ['path' => $config['path'], 'autorename' => false]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Dropbox create_folder failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
