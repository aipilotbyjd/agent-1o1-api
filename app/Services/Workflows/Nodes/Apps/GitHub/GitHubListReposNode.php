<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubListReposNode extends AppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.list_repos';
    }

    public function name(): string
    {
        return 'GitHub: List Repos';
    }

    public function description(): string
    {
        return 'List repositories for the authenticated user or an organization.';
    }

    public function icon(): string
    {
        return 'folder';
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
        return 'https://docs.github.com/en/rest/repos/repos#list-repositories-for-the-authenticated-user';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'owner' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'repos' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $http = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->withHeaders(['Accept' => 'application/vnd.github+json']);

        $org = $config['owner'] ?? null;
        $url = $org ? self::BASE_URL."/orgs/{$org}/repos" : self::BASE_URL.'/user/repos';

        $response = $http->get($url, ['per_page' => $config['per_page'] ?? 30]);
        $ok = $response->successful();

        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GitHub list_repos failed: '.$response->body());
        }

        return ['repos' => $response->json() ?? []];
    }
}
