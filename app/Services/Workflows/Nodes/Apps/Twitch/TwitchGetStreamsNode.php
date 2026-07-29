<?php

namespace App\Services\Workflows\Nodes\Apps\Twitch;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TwitchGetStreamsNode extends AppNode
{
    public function type(): string
    {
        return 'twitch.get_streams';
    }

    public function name(): string
    {
        return 'Twitch: Get Streams';
    }

    public function description(): string
    {
        return 'Get live streams from Twitch.';
    }

    public function icon(): string
    {
        return 'tv';
    }

    public function color(): string
    {
        return '#9146ff';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://dev.twitch.tv/docs/api/reference/#get-streams';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['user_login' => ['type' => 'string'], 'game_id' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'default' => 20]]];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['data' => ['type' => 'array']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $response = $this->twitchHttp($credential)->get('/streams', array_filter(['user_login' => $config['user_login'] ?? null, 'game_id' => $config['game_id'] ?? null, 'first' => $config['limit'] ?? 20]));
            if (! $response->successful()) {
                throw new \RuntimeException('Twitch get_streams failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return $response->json();
        } catch (\Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }

    private function twitchHttp(Credential $credential): PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($credential->data['token'] ?? '')->withHeaders(['Client-Id' => $credential->data['client_id'] ?? ''])->baseUrl('https://api.twitch.tv/helix');
    }
}
