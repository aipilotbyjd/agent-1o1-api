<?php

namespace App\Services\Workflows\Nodes\Apps\Notion;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class NotionAppendBlocksNode extends HttpAppNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->withHeaders(['Notion-Version' => '2022-06-28'])
            ->patch(self::BASE_URL."/blocks/{$config['block_id']}/children", [
                'children' => $config['children'],
            ]));

        return $data;
    }
}
