<?php

namespace App\Services\Workflows\Nodes\Apps\Trello;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TrelloListCardsNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function type(): string
    {
        return 'trello.list_cards';
    }

    public function name(): string
    {
        return 'Trello: List Cards';
    }

    public function description(): string
    {
        return 'List cards on a Trello list.';
    }

    public function icon(): string
    {
        return 'list';
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
        return 'https://developer.atlassian.com/cloud/trello/rest/api-group-lists/#api-lists-id-cards-get';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'list_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'list_id' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cards' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['api_key'] ?? '';
        $token = $credential->data['token'] ?? '';

        $response = Http::timeout(15)
            ->get(self::BASE_URL."/lists/{$config['list_id']}/cards", [
                'key' => $apiKey,
                'token' => $token,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Trello list_cards failed: '.$response->body());
        }

        return ['cards' => $response->json() ?? []];
    }
}
