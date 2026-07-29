<?php

namespace App\Services\Workflows\Nodes\Apps\Discord;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DiscordCreateChannelNode extends AppNode
{
    private const BASE_URL = 'https://discord.com/api/v10';

    public function type(): string
    {
        return 'discord.create_channel';
    }

    public function name(): string
    {
        return 'Discord: Create Channel';
    }

    public function description(): string
    {
        return 'Create a new Discord channel in a guild.';
    }

    public function icon(): string
    {
        return 'hash';
    }

    public function color(): string
    {
        return '#5865f2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'guild_id', 'name'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'guild_id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'type' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL."/guilds/{$config['guild_id']}/channels", [
                'name' => $config['name'],
                'type' => $config['type'] ?? 0,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Discord create_channel failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
