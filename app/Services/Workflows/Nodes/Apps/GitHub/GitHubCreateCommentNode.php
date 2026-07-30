<?php

namespace App\Services\Workflows\Nodes\Apps\GitHub;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GitHubCreateCommentNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.github.com';

    public function type(): string
    {
        return 'github.create_comment';
    }

    public function name(): string
    {
        return 'GitHub: Create Comment';
    }

    public function description(): string
    {
        return 'Add a comment to an issue or pull request.';
    }

    public function icon(): string
    {
        return 'message-circle';
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
        return 'https://docs.github.com/en/rest/issues/comments#create-an-issue-comment';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'repo', 'issue_number', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'repo' => ['type' => 'string'],
                'issue_number' => ['type' => 'integer'],
                'body' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'url' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post(self::BASE_URL."/repos/{$config['repo']}/issues/{$config['issue_number']}/comments", [
                'body' => $config['body'],
            ]));

        return [
            'id' => $data['id'] ?? null,
            'url' => $data['html_url'] ?? null,
        ];
    }
}
