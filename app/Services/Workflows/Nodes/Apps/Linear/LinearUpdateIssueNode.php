<?php

namespace App\Services\Workflows\Nodes\Apps\Linear;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class LinearUpdateIssueNode extends AppNode
{
    private const GRAPHQL_URL = 'https://api.linear.app/graphql';

    public function type(): string
    {
        return 'linear.update_issue';
    }

    public function name(): string
    {
        return 'Linear: Update Issue';
    }

    public function description(): string
    {
        return 'Update an existing Linear issue.';
    }

    public function icon(): string
    {
        return 'edit';
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
            'required' => ['credential_id', 'issue_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'issue_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'state_id' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'issueUpdate' => ['type' => 'object'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['api_key'] ?? '';

        $query = <<<'GRAPHQL'
        mutation IssueUpdate($id: String!, $input: IssueUpdateInput!) {
            issueUpdate(id: $id, input: $input) {
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
                    'id' => $config['issue_id'],
                    'input' => array_filter([
                        'title' => $config['title'] ?? null,
                        'description' => $config['description'] ?? null,
                        'stateId' => $config['state_id'] ?? null,
                    ]),
                ],
            ]);

        $data = $response->json() ?? [];
        $ok = $response->successful() && ! isset($data['errors']);
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Linear update_issue error: '.json_encode($data['errors'] ?? $response->body()));
        }

        return $data['data'] ?? [];
    }
}
