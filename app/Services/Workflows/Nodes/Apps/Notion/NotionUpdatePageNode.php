<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class NotionUpdatePageNode extends HttpAppNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->withHeaders(['Notion-Version' => '2022-06-28'])
            ->patch(self::BASE_URL."/pages/{$config['page_id']}", [
                'properties' => $config['properties'],
            ]));

        return $data;
    }
}
