<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class NotionCreatePageNode extends HttpAppNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->withHeaders(['Notion-Version' => '2022-06-28'])
            ->post(self::BASE_URL.'/pages', [
                'parent' => isset($config['database_id']) ? ['database_id' => $config['database_id']] : [],
                'properties' => $config['properties'],
                'children' => $config['children'] ?? [],
            ]));

        return $data;
    }
}
