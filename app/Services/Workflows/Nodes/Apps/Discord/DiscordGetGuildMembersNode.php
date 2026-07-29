<?php

namespace App\Services\Workflows\Nodes\Apps\Discord;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DiscordGetGuildMembersNode extends AppNode
{
    private const BASE_URL = 'https://discord.com/api/v10';

    public function type(): string
    {
        return 'discord.get_guild_members';
    }

    public function name(): string
    {
        return 'Discord: Get Guild Members';
    }

    public function description(): string
    {
        return 'Get members of a Discord guild.';
    }

    public function icon(): string
    {
        return 'users';
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
            'required' => ['credential_id', 'guild_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'guild_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'members' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/guilds/{$config['guild_id']}/members", [
                'limit' => $config['limit'] ?? 100,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Discord get_members failed: '.$response->body());
        }

        return ['members' => $response->json() ?? []];
    }
}
