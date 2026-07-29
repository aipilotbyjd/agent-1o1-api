<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GitHubCreatePullRequestNode extends AppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.create_pull_request';
    }

    public function name(): string
    {
        return 'GitHub: Create Pull Request';
    }

    public function description(): string
    {
        return 'Create a new pull request in a repository.';
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
        return 'https://docs.github.com/en/rest/pulls/pulls#create-a-pull-request';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'repo', 'title', 'head', 'base'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'head' => ['type' => 'string'],
                'base' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'number' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post(self::BASE_URL."/repos/{$config['repo']}/pulls", [
                'title' => $config['title'],
                'head' => $config['head'],
                'base' => $config['base'],
                'body' => $config['body'] ?? '',
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GitHub create_pull_request failed: '.$response->body());
        }

        $data = $response->json() ?? [];

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
