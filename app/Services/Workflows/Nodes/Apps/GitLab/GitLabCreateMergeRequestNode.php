<?php

namespace App\Services\Workflows\Nodes\Apps\GitLab;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GitLabCreateMergeRequestNode extends AppNode
{
    public function type(): string
    {
        return 'gitlab.create_merge_request';
    }

    public function name(): string
    {
        return 'GitLab: Create Merge Request';
    }

    public function description(): string
    {
        return 'Create a merge request in a GitLab project.';
    }

    public function icon(): string
    {
        return 'git-merge';
    }

    public function color(): string
    {
        return '#fc6d26';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://docs.gitlab.com/ee/api/merge_requests.html#create-mr';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_id', 'title', 'source_branch'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'source_branch' => ['type' => 'string'],
                'target_branch' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'remove_source_branch' => ['type' => 'boolean'],
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

        $baseUrl = $credential->data['base_url'] ?? 'https://gitlab.com';
        $projectId = urlencode($config['project_id']);

        $response = Http::timeout(15)
            ->withHeaders(['PRIVATE-TOKEN' => $credential->data['access_token'] ?? $credential->data['api_key'] ?? ''])
            ->post("{$baseUrl}/api/v4/projects/{$projectId}/merge_requests", array_filter([
                'title' => $config['title'],
                'source_branch' => $config['source_branch'],
                'target_branch' => $config['target_branch'] ?? 'main',
                'description' => $config['description'] ?? null,
                'remove_source_branch' => $config['remove_source_branch'] ?? null,
            ]));

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GitLab create_merge_request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
