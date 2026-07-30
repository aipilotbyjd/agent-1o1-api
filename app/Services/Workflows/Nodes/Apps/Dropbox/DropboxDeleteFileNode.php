<?php

namespace App\Services\Workflows\Nodes\Apps\Dropbox;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class DropboxDeleteFileNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.dropboxapi.com/2';

    public function type(): string
    {
        return 'dropbox.delete_file';
    }

    public function name(): string
    {
        return 'Dropbox: Delete File';
    }

    public function description(): string
    {
        return 'Delete a file or folder in Dropbox.';
    }

    public function icon(): string
    {
        return 'trash-2';
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
        return ['type' => 'object', 'properties' => ['deleted' => ['type' => 'boolean']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->post(self::BASE_URL.'/files/delete_v2', ['path' => $config['path']]));

        return ['deleted' => true];
    }
}
