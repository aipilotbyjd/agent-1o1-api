<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GitHubListCommitsNode extends AppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.list_commits';
    }

    public function name(): string
    {
        return 'GitHub: List Commits';
    }

    public function description(): string
    {
        return 'List commits in a repository.';
    }

    public function icon(): string
    {
        return 'git-commit';
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
        return 'https://docs.github.com/en/rest/commits/commits#list-commits';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'repo'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'sha' => ['type' => 'string'],
                'path' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'commits' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/repos/{$config['repo']}/commits", [
                'sha' => $config['sha'] ?? null,
                'path' => $config['path'] ?? null,
                'per_page' => $config['per_page'] ?? 30,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GitHub list_commits failed: '.$response->body());
        }

        return ['commits' => $response->json() ?? []];
    }
}
