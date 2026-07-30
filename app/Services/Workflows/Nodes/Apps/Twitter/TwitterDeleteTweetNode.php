<?php

namespace App\Services\Workflows\Nodes\Apps\Twitter;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class TwitterDeleteTweetNode extends HttpAppNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http->get(self::BASE_URL.'/tweets/search/recent', ['query' => $config['query'], 'max_results' => $config['max_results'] ?? 10]));

        return $data;
    }
}
