<?php

namespace App\Services\Workflows\Nodes\Apps\Linear;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class LinearCreateCommentNode extends AppNode
{
    private const GRAPHQL_URL = 'https://api.linear.app/graphql';

    public function type(): string
    {
        return 'linear.create_comment';
    }

    public function name(): string
    {
        return 'Linear: Create Comment';
    }

    public function description(): string
    {
        return 'Add a comment to a Linear issue.';
    }

    public function icon(): string
    {
        return 'message-circle';
    }

    public function color(): string
    {
        return '#5e6ad2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'issue_id', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'issue_id' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'commentCreate' => ['type' => 'object'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['api_key'] ?? '';

        $query = <<<'GRAPHQL'
        mutation CommentCreate($input: CommentCreateInput!) {
            commentCreate(input: $input) { success }
        }
        GRAPHQL;

        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => $apiKey])
            ->post(self::GRAPHQL_URL, [
                'query' => $query,
                'variables' => [
                    'input' => [
                        'issueId' => $config['issue_id'],
                        'body' => $config['body'],
                    ],
                ],
            ]);

        $data = $response->json() ?? [];
        $ok = $response->successful() && ! isset($data['errors']);
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Linear create_comment error: '.json_encode($data['errors'] ?? $response->body()));
        }

        return $data['data'] ?? [];
    }
}
