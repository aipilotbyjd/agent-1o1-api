<?php

namespace App\Services\Workflows\Nodes\Apps\GitLab;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabListPipelinesNode extends AppNode
{
    public function type(): string
    {
        return 'gitlab.list_pipelines';
    }

    public function name(): string
    {
        return 'GitLab: List Pipelines';
    }

    public function description(): string
    {
        return 'List CI/CD pipelines in a GitLab project.';
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
        return 'https://docs.gitlab.com/ee/api/pipelines.html#list-project-pipelines';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_id' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'ref' => ['type' => 'string'],
                'per_page' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pipelines' => ['type' => 'array'],
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
            ->get("{$baseUrl}/api/v4/projects/{$projectId}/pipelines", [
                'status' => $config['status'] ?? null,
                'per_page' => $config['per_page'] ?? 20,
                'ref' => $config['ref'] ?? null,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GitLab list_pipelines failed: '.$response->body());
        }

        return ['pipelines' => $response->json() ?? []];
    }
}
