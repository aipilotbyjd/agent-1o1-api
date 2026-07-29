<?php

namespace App\Services\Workflows\Nodes\Apps\Trello;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TrelloMoveCardNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function type(): string
    {
        return 'trello.move_card';
    }

    public function name(): string
    {
        return 'Trello: Move Card';
    }

    public function description(): string
    {
        return 'Move a card to a different list.';
    }

    public function icon(): string
    {
        return 'arrow-right';
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
        return 'https://developer.atlassian.com/cloud/trello/rest/api-group-cards/#api-cards-id-put';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'card_id', 'list_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'card_id' => ['type' => 'string'],
                'list_id' => ['type' => 'string'],
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
            ->put(self::BASE_URL."/cards/{$config['card_id']}", [
                'key' => $apiKey,
                'token' => $token,
                'idList' => $config['list_id'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Trello move_card failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
