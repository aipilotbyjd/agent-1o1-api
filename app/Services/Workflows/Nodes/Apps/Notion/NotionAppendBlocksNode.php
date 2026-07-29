<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class NotionAppendBlocksNode extends AppNode
{
    private const BASE_URL = 'https://api.notion.com/v1';

    public function type(): string
    {
        return 'notion.append_blocks';
    }

    public function name(): string
    {
        return 'Notion: Append Blocks';
    }

    public function description(): string
    {
        return 'Append children blocks to a Notion block.';
    }

    public function icon(): string
    {
        return 'layers';
    }

    public function color(): string
    {
        return '#ffffff';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.notion.com/reference/patch-block-children';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'block_id', 'children'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'block_id' => ['type' => 'string'],
                'children' => ['type' => 'array'],
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

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->withHeaders(['Notion-Version' => '2022-06-28'])
            ->patch(self::BASE_URL."/blocks/{$config['block_id']}/children", [
                'children' => $config['children'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Notion append_blocks failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
