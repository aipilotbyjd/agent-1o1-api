<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GitHubListPullRequestsNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.list_pull_requests';
    }

    public function name(): string
    {
        return 'GitHub: List Pull Requests';
    }

    public function description(): string
    {
        return 'List pull requests in a repository.';
    }

    public function icon(): string
    {
        return 'git-pull-request';
    }

    public function color(): string
    {
        return '#24292e';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://docs.github.com/en/rest/pulls/pulls#list-pull-requests';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'repo'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'state' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pull_requests' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->get(self::BASE_URL."/repos/{$config['repo']}/pulls", [
                'state' => $config['state'] ?? 'open',
                'per_page' => $config['per_page'] ?? 30,
            ]));

        return ['pull_requests' => $data];
    }
}
