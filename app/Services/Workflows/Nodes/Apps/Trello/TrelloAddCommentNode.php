<?php

namespace App\Services\Workflows\Nodes\Apps\Trello;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrelloAddCommentNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function type(): string
    {
        return 'trello.add_comment';
    }

    public function name(): string
    {
        return 'Trello: Add Comment';
    }

    public function description(): string
    {
        return 'Add a comment to a Trello card.';
    }

    public function icon(): string
    {
        return 'message-circle';
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
        return 'https://developer.atlassian.com/cloud/trello/rest/api-group-cards/#api-cards-id-actions-comments-post';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'card_id', 'text'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'card_id' => ['type' => 'string'],
                'text' => ['type' => 'string'],
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
            ->post(self::BASE_URL."/cards/{$config['card_id']}/actions/comments", [
                'key' => $apiKey,
                'token' => $token,
                'text' => $config['text'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Trello add_comment failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
