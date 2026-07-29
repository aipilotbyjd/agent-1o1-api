<?php

namespace App\Services\Workflows\Nodes\Apps\Twitter;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TwitterDeleteTweetNode extends AppNode
{
    private const BASE_URL = 'https://api.twitter.com/2';

    public function type(): string
    {
        return 'twitter.search_tweets';
    }

    public function name(): string
    {
        return 'Twitter: Search Tweets';
    }

    public function description(): string
    {
        return 'Search recent tweets by query.';
    }

    public function icon(): string
    {
        return 'search';
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
            'type' => 'object', 'required' => ['credential_id', 'query'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'query' => ['type' => 'string'], 'max_results' => ['type' => 'integer']],
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
        $response = Http::timeout(15)->withToken($credential->data['token'] ?? '')->get(self::BASE_URL.'/tweets/search/recent', ['query' => $config['query'], 'max_results' => $config['max_results'] ?? 10]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('Twitter search_tweets failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
