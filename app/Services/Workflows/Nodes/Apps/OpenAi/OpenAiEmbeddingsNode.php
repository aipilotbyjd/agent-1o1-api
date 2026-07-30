<?php

namespace App\Services\Workflows\Nodes\Apps\OpenAi;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEmbeddingsNode extends AppNode
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function type(): string
    {
        return 'openai.embeddings';
    }

    public function name(): string
    {
        return 'OpenAI: Embeddings';
    }

    public function description(): string
    {
        return 'Generate text embeddings using OpenAI.';
    }

    public function icon(): string
    {
        return 'grid';
    }

    public function color(): string
    {
        return '#000000';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'input'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'input' => ['type' => 'string'], 'model' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['embedding' => ['type' => 'array'], 'usage' => ['type' => 'object']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $response = Http::timeout(30)->withToken($credential->data['api_key'] ?? '')->post(self::BASE_URL.'/embeddings', [
            'model' => $config['model'] ?? 'text-embedding-3-small', 'input' => $config['input'],
        ]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new RuntimeException('OpenAI embeddings failed: '.$response->body());
        }
        $data = $response->json();

        return ['embedding' => $data['data'][0]['embedding'] ?? [], 'usage' => $data['usage'] ?? []];
    }
}
