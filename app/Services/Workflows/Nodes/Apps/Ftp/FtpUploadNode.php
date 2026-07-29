<?php

namespace App\Services\Workflows\Nodes\Apps\Ftp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Storage;

class FtpUploadNode extends AppNode
{
    public function type(): string
    {
        return 'ftp.list_files';
    }

    public function name(): string
    {
        return 'FTP: List Files';
    }

    public function description(): string
    {
        return 'List files in an FTP directory.';
    }

    public function icon(): string
    {
        return 'folder';
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
            'type' => 'object', 'required' => ['credential_id'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'path' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['files' => ['type' => 'array']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $disk = Storage::build(['driver' => 'ftp', 'host' => $credential->data['host'] ?? '', 'username' => $credential->data['username'] ?? '', 'password' => $credential->data['password'] ?? '', 'port' => (int) ($credential->data['port'] ?? 21)]);
        $result = ['files' => $disk->files($config['path'] ?? '')];
        $this->recordMetric($run, true, $startedAt);

        return $result;
    }
}
