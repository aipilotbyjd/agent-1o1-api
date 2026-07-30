<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class JiraTransitionIssueNode extends JiraNode
{
    public function type(): string
    {
        return 'jira.transition_issue';
    }

    public function name(): string
    {
        return 'Jira: Transition Issue';
    }

    public function description(): string
    {
        return 'Transition a Jira issue to a new status.';
    }

    public function icon(): string
    {
        return 'arrow-right-circle';
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
            'required' => ['credential_id', 'issue_key', 'transition_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'issue_key' => ['type' => 'string'],
                'transition_id' => ['type' => 'string'],
                'domain' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transitioned' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post($this->baseUrl($credential, $config)."/issue/{$config['issue_key']}/transitions", [
                'transition' => ['id' => $config['transition_id']],
            ]));

        return ['transitioned' => true];
    }
}
