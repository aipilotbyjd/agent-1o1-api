<?php

namespace App\Services\Workflows\Nodes\Apps\Slack;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class SlackUploadFileNode extends AppNode
{
    private const BASE_URL = 'https://slack.com/api';

    public function type(): string
    {
        return 'slack.upload_file';
    }

    public function name(): string
    {
        return 'Slack: Upload File';
    }

    public function description(): string
    {
        return 'Upload a file to a Slack channel.';
    }

    public function icon(): string
    {
        return 'upload';
    }

    public function color(): string
    {
        return '#4a154b';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://api.slack.com/methods/files.upload';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'channel', 'content'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'channel' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'filename' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => ['type' => 'object'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL.'/files.upload', [
                'channels' => $config['channel'],
                'content' => $config['content'],
                'filename' => $config['filename'] ?? 'file.txt',
            ]);

        $body = $response->json() ?? [];
        $ok = $response->successful() && ($body['ok'] ?? false) === true;

        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Slack upload_file error: '.($body['error'] ?? 'unknown'));
        }

        return ['file' => $body['file'] ?? []];
    }
}
