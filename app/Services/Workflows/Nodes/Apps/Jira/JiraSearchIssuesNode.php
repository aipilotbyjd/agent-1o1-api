<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class JiraSearchIssuesNode extends JiraNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->get($this->baseUrl($credential, $config).'/search', [
                'jql' => $config['jql'] ?? '',
                'maxResults' => $config['max_results'] ?? 25,
            ]));

        return $data;
    }
}
