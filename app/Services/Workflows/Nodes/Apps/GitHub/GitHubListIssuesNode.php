<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GitHubListIssuesNode extends AppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.list_issues';
    }

    public function name(): string
    {
        return 'GitHub: List Issues';
    }

    public function description(): string
    {
        return 'List issues in a repository.';
    }

    public function icon(): string
    {
        return 'list';
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
        return 'https://docs.github.com/en/rest/issues/issues#list-repository-issues';
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
                'issues' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/repos/{$config['repo']}/issues", [
                'state' => $config['state'] ?? 'open',
                'per_page' => $config['per_page'] ?? 30,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GitHub list_issues failed: '.$response->body());
        }

        return ['issues' => $response->json() ?? []];
    }
}
