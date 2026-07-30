<?php

namespace App\Services\Workflows\Nodes\Apps\Linear;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LinearListIssuesNode extends AppNode
{
    private const GRAPHQL_URL = 'https://api.linear.app/graphql';

    public function type(): string
    {
        return 'linear.list_issues';
    }

    public function name(): string
    {
        return 'Linear: List Issues';
    }

    public function description(): string
    {
        return 'List issues from Linear.';
    }

    public function icon(): string
    {
        return 'list';
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
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
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

        $apiKey = $credential->data['api_key'] ?? '';

        $query = <<<'GRAPHQL'
        query Issues($first: Int) {
            issues(first: $first) {
                nodes { id identifier title state { name } url }
            }
        }
        GRAPHQL;

        $response = Http::timeout(15)
            ->withHeaders(['Authorization' => $apiKey])
            ->post(self::GRAPHQL_URL, [
                'query' => $query,
                'variables' => ['first' => (int) ($config['limit'] ?? 25)],
            ]);

        $data = $response->json() ?? [];
        $ok = $response->successful() && ! isset($data['errors']);
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Linear list_issues error: '.json_encode($data['errors'] ?? $response->body()));
        }

        return $data['data'] ?? [];
    }
}
