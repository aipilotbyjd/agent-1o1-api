<?php

namespace App\Services\Workflows\Nodes\Apps\Ftp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Storage;

class FtpDownloadNode extends AppNode
{
    public function type(): string
    {
        return 'ftp.delete';
    }

    public function name(): string
    {
        return 'FTP: Delete File';
    }

    public function description(): string
    {
        return 'Delete a file from an FTP server.';
    }

    public function icon(): string
    {
        return 'trash-2';
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
            'type' => 'object', 'required' => ['credential_id', 'path'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'path' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['deleted' => ['type' => 'boolean']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $disk = Storage::build(['driver' => 'ftp', 'host' => $credential->data['host'] ?? '', 'username' => $credential->data['username'] ?? '', 'password' => $credential->data['password'] ?? '', 'port' => (int) ($credential->data['port'] ?? 21)]);
        $disk->delete($config['path']);
        $this->recordMetric($run, true, $startedAt);

        return ['deleted' => true];
    }
}
