<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class NotionCreatePageNode extends AppNode
{
    private const BASE_URL = 'https://api.notion.com/v1';

    public function type(): string
    {
        return 'notion.create_page';
    }

    public function name(): string
    {
        return 'Notion: Create Page';
    }

    public function description(): string
    {
        return 'Create a new page in a Notion database or parent page.';
    }

    public function icon(): string
    {
        return 'file-plus';
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
        return 'https://developers.notion.com/reference/post-page';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'properties'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'database_id' => ['type' => 'string'],
                'properties' => ['type' => 'object'],
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
            ->post(self::BASE_URL.'/pages', [
                'parent' => isset($config['database_id']) ? ['database_id' => $config['database_id']] : [],
                'properties' => $config['properties'],
                'children' => $config['children'] ?? [],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Notion create_page failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
