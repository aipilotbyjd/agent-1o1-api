<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class JiraUpdateIssueNode extends JiraNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->put($this->baseUrl($credential, $config)."/issue/{$config['issue_key']}", [
                'fields' => $config['fields'],
            ]));

        return ['updated' => true];
    }
}
