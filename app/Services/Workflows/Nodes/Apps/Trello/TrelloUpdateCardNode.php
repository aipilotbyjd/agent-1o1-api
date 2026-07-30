<?php

namespace App\Services\Workflows\Nodes\Apps\Trello;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrelloUpdateCardNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function type(): string
    {
        return 'trello.update_card';
    }

    public function name(): string
    {
        return 'Trello: Update Card';
    }

    public function description(): string
    {
        return 'Update a Trello card\'s properties.';
    }

    public function icon(): string
    {
        return 'edit';
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
            'required' => ['credential_id', 'card_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'card_id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'closed' => ['type' => 'boolean'],
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
                'name' => $config['name'] ?? null,
                'desc' => $config['description'] ?? null,
                'closed' => $config['closed'] ?? null,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Trello update_card failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
