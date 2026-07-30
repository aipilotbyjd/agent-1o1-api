<?php

namespace App\Services\Workflows\Nodes\Apps\GitLab;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabListIssuesNode extends AppNode
{
    public function type(): string
    {
        return 'gitlab.list_issues';
    }

    public function name(): string
    {
        return 'GitLab: List Issues';
    }

    public function description(): string
    {
        return 'List issues from a GitLab project.';
    }

    public function icon(): string
    {
        return 'list';
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
        return 'https://docs.gitlab.com/ee/api/issues.html#list-project-issues';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_id' => ['type' => 'string'],
                'state' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
                'labels' => ['type' => 'string'],
                'assignee' => ['type' => 'string'],
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

        $baseUrl = $credential->data['base_url'] ?? 'https://gitlab.com';
        $projectId = urlencode($config['project_id']);

        $response = Http::timeout(15)
            ->withHeaders(['PRIVATE-TOKEN' => $credential->data['access_token'] ?? $credential->data['api_key'] ?? ''])
            ->get("{$baseUrl}/api/v4/projects/{$projectId}/issues", [
                'state' => $config['state'] ?? 'opened',
                'per_page' => $config['per_page'] ?? 20,
                'labels' => $config['labels'] ?? null,
                'assignee_username' => $config['assignee'] ?? null,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GitLab list_issues failed: '.$response->body());
        }

        return ['issues' => $response->json() ?? []];
    }
}
