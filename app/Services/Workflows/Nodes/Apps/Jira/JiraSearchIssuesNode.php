<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class JiraSearchIssuesNode extends AppNode
{
    public function type(): string
    {
        return 'jira.search_issues';
    }

    public function name(): string
    {
        return 'Jira: Search Issues';
    }

    public function description(): string
    {
        return 'Search Jira issues using JQL.';
    }

    public function icon(): string
    {
        return 'search';
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
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'jql' => ['type' => 'string'],
                'max_results' => ['type' => 'integer'],
                'domain' => ['type' => 'string'],
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

        $domain = $credential->data['domain'] ?? $config['domain'] ?? '';

        $response = Http::timeout(15)
            ->withBasicAuth($credential->data['email'] ?? '', $credential->data['api_token'] ?? $credential->data['password'] ?? '')
            ->get("https://{$domain}/rest/api/3/search", [
                'jql' => $config['jql'] ?? '',
                'maxResults' => $config['max_results'] ?? 25,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Jira search_issues failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
