<?php

namespace App\Services\Workflows\Nodes\Apps\GitLab;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabUpdateIssueNode extends AppNode
{
    public function type(): string
    {
        return 'gitlab.update_issue';
    }

    public function name(): string
    {
        return 'GitLab: Update Issue';
    }

    public function description(): string
    {
        return 'Update an existing issue in a GitLab project.';
    }

    public function icon(): string
    {
        return 'edit';
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
        return 'https://docs.gitlab.com/ee/api/issues.html#edit-an-issue';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_id', 'issue_iid'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_id' => ['type' => 'string'],
                'issue_iid' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'state_event' => ['type' => 'string'],
                'labels' => ['type' => 'string'],
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
            ->put("{$baseUrl}/api/v4/projects/{$projectId}/issues/{$config['issue_iid']}", array_filter([
                'title' => $config['title'] ?? null,
                'description' => $config['description'] ?? null,
                'state_event' => $config['state_event'] ?? null,
                'labels' => $config['labels'] ?? null,
            ]));

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GitLab update_issue failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
