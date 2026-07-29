<?php

namespace App\Services\Workflows\Nodes\Apps\Linear;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class LinearCreateIssueNode extends AppNode
{
    private const GRAPHQL_URL = 'https://api.linear.app/graphql';

    public function type(): string
    {
        return 'linear.create_issue';
    }

    public function name(): string
    {
        return 'Linear: Create Issue';
    }

    public function description(): string
    {
        return 'Create a new issue in Linear.';
    }

    public function icon(): string
    {
        return 'plus-circle';
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
            'required' => ['credential_id', 'team_id', 'title'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'team_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'priority' => ['type' => 'integer'],
                'assignee_id' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'issueCreate' => ['type' => 'object'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['api_key'] ?? '';

        $query = <<<'GRAPHQL'
        mutation IssueCreate($input: IssueCreateInput!) {
            issueCreate(input: $input) {
                success
                issue { id identifier title url }
            }
        }
        GRAPHQL;

        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => $apiKey])
            ->post(self::GRAPHQL_URL, [
                'query' => $query,
                'variables' => [
                    'input' => array_filter([
                        'teamId' => $config['team_id'],
                        'title' => $config['title'],
                        'description' => $config['description'] ?? null,
                        'priority' => $config['priority'] ?? null,
                        'assigneeId' => $config['assignee_id'] ?? null,
                    ]),
                ],
            ]);

        $data = $response->json() ?? [];
        $ok = $response->successful() && ! isset($data['errors']);
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Linear create_issue error: '.json_encode($data['errors'] ?? $response->body()));
        }

        return $data['data'] ?? [];
    }
}
