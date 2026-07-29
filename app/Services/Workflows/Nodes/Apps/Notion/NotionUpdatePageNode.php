<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class NotionUpdatePageNode extends AppNode
{
    private const BASE_URL = 'https://api.notion.com/v1';

    public function type(): string
    {
        return 'notion.update_page';
    }

    public function name(): string
    {
        return 'Notion: Update Page';
    }

    public function description(): string
    {
        return 'Update properties of a Notion page.';
    }

    public function icon(): string
    {
        return 'edit';
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
        return 'https://developers.notion.com/reference/patch-page';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'page_id', 'properties'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'page_id' => ['type' => 'string'],
                'properties' => ['type' => 'object'],
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
            ->patch(self::BASE_URL."/pages/{$config['page_id']}", [
                'properties' => $config['properties'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Notion update_page failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
