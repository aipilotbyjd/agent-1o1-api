<?php

namespace App\Services\Workflows\Nodes\Apps\Twitter;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TwitterPostTweetNode extends AppNode
{
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
        return 'message-square';
    }

    public function color(): string
    {
        return '#1da1f2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developer.twitter.com/en/docs/twitter-api/tweets/manage-tweets/api-reference/post-tweets';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['data' => ['type' => 'object']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $response = Http::acceptJson()->asJson()->withToken($credential->data['token'] ?? '')->baseUrl('https://api.twitter.com/2')->post('/tweets', ['text' => $config['text'] ?? '']);
            if (! $response->successful()) {
                throw new RuntimeException('Twitter post_tweet failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return $response->json();
        } catch (Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }
}
