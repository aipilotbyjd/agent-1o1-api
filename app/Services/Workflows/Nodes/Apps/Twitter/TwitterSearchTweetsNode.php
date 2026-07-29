<?php

namespace App\Services\Workflows\Nodes\Apps\Twitter;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TwitterSearchTweetsNode extends AppNode
{
    private const BASE_URL = 'https://api.twitter.com/2';

    public function type(): string
    {
        return 'twitter.post_tweet';
    }

    public function name(): string
    {
        return 'Twitter: Post Tweet';
    }

    public function description(): string
    {
        return 'Post a tweet to Twitter/X.';
    }

    public function icon(): string
    {
        return 'message-circle';
    }

    public function color(): string
    {
        return '#1da1f2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'text'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'text' => ['type' => 'string']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->post(self::BASE_URL.'/tweets', ['text' => $config['text']]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Twitter post_tweet failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
