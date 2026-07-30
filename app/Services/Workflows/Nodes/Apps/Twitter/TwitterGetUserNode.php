<?php

namespace App\Services\Workflows\Nodes\Apps\Twitter;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class TwitterGetUserNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.twitter.com/2';

    public function type(): string
    {
        return 'twitter.get_user';
    }

    public function name(): string
    {
        return 'Twitter: Get User';
    }

    public function description(): string
    {
        return 'Look up a Twitter user by username.';
    }

    public function icon(): string
    {
        return 'user';
    }

    public function color(): string
    {
        return '#1da1f2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'username'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'username' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->get(self::BASE_URL."/users/by/username/{$config['username']}"));

        return $data;
    }
}
