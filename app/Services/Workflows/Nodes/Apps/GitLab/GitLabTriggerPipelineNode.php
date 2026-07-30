<?php

namespace App\Services\Workflows\Nodes\Apps\GitLab;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabTriggerPipelineNode extends AppNode
{
    public function type(): string
    {
        return 'gitlab.trigger_pipeline';
    }

    public function name(): string
    {
        return 'GitLab: Trigger Pipeline';
    }

    public function description(): string
    {
        return 'Trigger a CI/CD pipeline in a GitLab project.';
    }

    public function icon(): string
    {
        return 'play';
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
        return 'https://docs.gitlab.com/ee/api/pipelines.html#create-a-pipeline';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_id' => ['type' => 'string'],
                'ref' => ['type' => 'string'],
                'variables' => ['type' => 'object'],
                'trigger_token' => ['type' => 'string'],
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
            ->post("{$baseUrl}/api/v4/projects/{$projectId}/trigger/pipeline", array_filter([
                'token' => $config['trigger_token'] ?? $credential->data['trigger_token'] ?? '',
                'ref' => $config['ref'] ?? 'main',
                'variables' => $config['variables'] ?? null,
            ]));

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('GitLab trigger_pipeline failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
