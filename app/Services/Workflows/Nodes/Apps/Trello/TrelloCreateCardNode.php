<?php

namespace App\Services\Workflows\Nodes\Apps\Trello;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TrelloCreateCardNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function type(): string
    {
        return 'trello.create_card';
    }

    public function name(): string
    {
        return 'Trello: Create Card';
    }

    public function description(): string
    {
        return 'Create a new card on a Trello list.';
    }

    public function icon(): string
    {
        return 'plus-square';
    }

    public function color(): string
    {
        return '#0079bf';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://developer.atlassian.com/cloud/trello/rest/api-group-cards/#api-cards-post';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'list_id', 'name'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'list_id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'due' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['api_key'] ?? '';
        $token = $credential->data['token'] ?? '';

        $response = Http::timeout(15)
            ->post(self::BASE_URL.'/cards', [
                'key' => $apiKey,
                'token' => $token,
                'idList' => $config['list_id'],
                'name' => $config['name'],
                'desc' => $config['description'] ?? null,
                'due' => $config['due'] ?? null,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Trello create_card failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
