<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class JiraUpdateIssueNode extends AppNode
{
    public function type(): string
    {
        return 'jira.update_issue';
    }

    public function name(): string
    {
        return 'Jira: Update Issue';
    }

    public function description(): string
    {
        return 'Update fields of an existing Jira issue.';
    }

    public function icon(): string
    {
        return 'edit';
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
            'required' => ['credential_id', 'issue_key', 'fields'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'issue_key' => ['type' => 'string'],
                'fields' => ['type' => 'object'],
                'domain' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'updated' => ['type' => 'boolean'],
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
            ->put("https://{$domain}/rest/api/3/issue/{$config['issue_key']}", [
                'fields' => $config['fields'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Jira update_issue failed: '.$response->body());
        }

        return ['updated' => true];
    }
}
