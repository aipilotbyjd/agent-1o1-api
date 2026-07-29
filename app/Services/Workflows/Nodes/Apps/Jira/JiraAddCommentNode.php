<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class JiraAddCommentNode extends AppNode
{
    public function type(): string
    {
        return 'jira.add_comment';
    }

    public function name(): string
    {
        return 'Jira: Add Comment';
    }

    public function description(): string
    {
        return 'Add a comment to a Jira issue.';
    }

    public function icon(): string
    {
        return 'message-circle';
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
            'required' => ['credential_id', 'issue_key', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'issue_key' => ['type' => 'string'],
                'body' => ['type' => 'string'],
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
            ->post("https://{$domain}/rest/api/3/issue/{$config['issue_key']}/comment", [
                'body' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $config['body']]],
                    ]],
                ],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Jira add_comment failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
