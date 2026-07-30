<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GitHubCreateIssueNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.create_issue';
    }

    public function name(): string
    {
        return 'GitHub: Create Issue';
    }

    public function description(): string
    {
        return 'Create a new issue in a repository.';
    }

    public function icon(): string
    {
        return 'alert-circle';
    }

    public function color(): string
    {
        return '#24292e';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://docs.github.com/en/rest/issues/issues#create-an-issue';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'repo', 'title'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'labels' => ['type' => 'array'],
                'assignees' => ['type' => 'array'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'number' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post(self::BASE_URL."/repos/{$config['repo']}/issues", [
                'title' => $config['title'],
                'body' => $config['body'] ?? '',
                'labels' => $config['labels'] ?? [],
                'assignees' => $config['assignees'] ?? [],
            ]));

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
