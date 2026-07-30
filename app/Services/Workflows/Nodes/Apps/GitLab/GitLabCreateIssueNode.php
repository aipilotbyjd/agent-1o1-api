<?php

namespace App\Services\Workflows\Nodes\Apps\GitLab;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabCreateIssueNode extends AppNode
{
    public function type(): string
    {
        return 'gitlab.create_issue';
    }

    public function name(): string
    {
        return 'GitLab: Create Issue';
    }

    public function description(): string
    {
        return 'Create a new issue in a GitLab project.';
    }

    public function icon(): string
    {
        return 'plus-circle';
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
        return 'https://docs.gitlab.com/ee/api/issues.html#create-an-issue';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_id', 'title'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'labels' => ['type' => 'string'],
                'assignee_ids' => ['type' => 'array'],
                'milestone_id' => ['type' => 'integer'],
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
            ->post("{$baseUrl}/api/v4/projects/{$projectId}/issues", array_filter([
                'title' => $config['title'],
                'description' => $config['description'] ?? null,
                'labels' => $config['labels'] ?? null,
                'assignee_ids' => $config['assignee_ids'] ?? null,
                'milestone_id' => $config['milestone_id'] ?? null,
            ]));

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GitLab create_issue failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
