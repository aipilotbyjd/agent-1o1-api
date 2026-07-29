<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GitHubGetRepoNode extends AppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.get_repo';
    }

    public function name(): string
    {
        return 'GitHub: Get Repository';
    }

    public function description(): string
    {
        return 'Get details of a specific repository.';
    }

    public function icon(): string
    {
        return 'book-open';
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
        return 'https://docs.github.com/en/rest/repos/repos#get-a-repository';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'repo'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/repos/{$config['repo']}");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GitHub get_repo failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
