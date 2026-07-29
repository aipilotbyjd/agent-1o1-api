<?php

namespace App\Services\Workflows\Nodes\Apps\Dropbox;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DropboxGetLinkNode extends AppNode
{
    private const BASE_URL = 'https://api.dropboxapi.com/2';

    public function type(): string
    {
        return 'dropbox.get_link';
    }

    public function name(): string
    {
        return 'Dropbox: Get Share Link';
    }

    public function description(): string
    {
        return 'Create a shared link for a Dropbox file.';
    }

    public function icon(): string
    {
        return 'link';
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post(self::BASE_URL.'/sharing/create_shared_link_with_settings', ['path' => $config['path']]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Dropbox get_link failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
