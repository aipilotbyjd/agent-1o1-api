<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class JiraCreateIssueNode extends AppNode
{
    public function type(): string
    {
        return 'jira.create_issue';
    }

    public function name(): string
    {
        return 'Jira: Create Issue';
    }

    public function description(): string
    {
        return 'Create a new issue in Jira.';
    }

    public function icon(): string
    {
        return 'plus-circle';
    }

    public function color(): string
    {
        return '#2684ff';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BASIC_AUTH;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'project_key', 'summary'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'project_key' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'issue_type' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'domain' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'key' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $domain = $credential->data['domain'] ?? $config['domain'] ?? '';

        $response = Http::timeout(15)
            ->withBasicAuth($credential->data['email'] ?? '', $credential->data['api_token'] ?? $credential->data['password'] ?? '')
            ->post("https://{$domain}/rest/api/3/issue", [
                'fields' => [
                    'project' => ['key' => $config['project_key']],
                    'summary' => $config['summary'],
                    'issuetype' => ['name' => $config['issue_type'] ?? 'Task'],
                    'description' => isset($config['description']) ? [
                        'type' => 'doc',
                        'version' => 1,
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => $config['description']]],
                        ]],
                    ] : null,
                ],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Jira create_issue failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
