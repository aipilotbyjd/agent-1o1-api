<?php

namespace App\Services\Workflows\Nodes\Apps\Ftp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Storage;

class FtpDeleteNode extends AppNode
{
    public function type(): string
    {
        return 'ftp.upload';
    }

    public function name(): string
    {
        return 'FTP: Upload File';
    }

    public function description(): string
    {
        return 'Upload content to an FTP server.';
    }

    public function icon(): string
    {
        return 'upload';
    }

    public function color(): string
    {
        return '#4a5568';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BASIC_AUTH;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'path', 'content'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'path' => ['type' => 'string'], 'content' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['uploaded' => ['type' => 'boolean']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $disk = Storage::build(['driver' => 'ftp', 'host' => $credential->data['host'] ?? '', 'username' => $credential->data['username'] ?? '', 'password' => $credential->data['password'] ?? '', 'port' => (int) ($credential->data['port'] ?? 21)]);
        $disk->put($config['path'], $config['content'] ?? '');
        $this->recordMetric($run, true, $startedAt);

        return ['uploaded' => true, 'path' => $config['path']];
    }
}
