<?php

namespace App\Services\Workflows\Nodes\Apps\Dropbox;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DropboxMoveFileNode extends AppNode
{
    private const BASE_URL = 'https://api.dropboxapi.com/2';

    public function type(): string
    {
        return 'dropbox.move_file';
    }

    public function name(): string
    {
        return 'Dropbox: Move File';
    }

    public function description(): string
    {
        return 'Move or rename a file in Dropbox.';
    }

    public function icon(): string
    {
        return 'move';
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
            'type' => 'object', 'required' => ['credential_id', 'from_path', 'to_path'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'from_path' => ['type' => 'string'], 'to_path' => ['type' => 'string']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post(self::BASE_URL.'/files/move_v2', ['from_path' => $config['from_path'], 'to_path' => $config['to_path']]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Dropbox move_file failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
