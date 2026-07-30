<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class NotionListDatabasesNode extends HttpAppNode
{
    private const BASE_URL = 'https://api.notion.com/v1';

    public function type(): string
    {
        return 'notion.list_databases';
    }

    public function name(): string
    {
        return 'Notion: List Databases';
    }

    public function description(): string
    {
        return 'Search and list Notion databases.';
    }

    public function icon(): string
    {
        return 'database';
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
        return 'https://developers.notion.com/reference/search';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'query' => ['type' => 'string'],
                'page_size' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->withHeaders(['Notion-Version' => '2022-06-28'])
            ->post(self::BASE_URL.'/search', array_filter([
                'filter' => ['value' => 'database', 'property' => 'object'],
                'page_size' => $config['page_size'] ?? 100,
                'query' => $config['query'] ?? null,
            ])));

        return $data;
    }
}
