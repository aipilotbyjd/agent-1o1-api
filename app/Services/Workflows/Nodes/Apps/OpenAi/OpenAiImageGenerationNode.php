<?php

namespace App\Services\Workflows\Nodes\Apps\OpenAi;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiImageGenerationNode extends AppNode
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function type(): string
    {
        return 'openai.image_generation';
    }

    public function name(): string
    {
        return 'OpenAI: Generate Image';
    }

    public function description(): string
    {
        return 'Generate an image using DALL-E.';
    }

    public function icon(): string
    {
        return 'image';
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
            'type' => 'object', 'required' => ['credential_id', 'prompt'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'prompt' => ['type' => 'string'], 'model' => ['type' => 'string'], 'n' => ['type' => 'integer'], 'size' => ['type' => 'string']],
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
        $response = Http::timeout(60)->withToken($credential->data['api_key'] ?? '')->post(self::BASE_URL.'/images/generations', [
            'model' => $config['model'] ?? 'dall-e-3', 'prompt' => $config['prompt'], 'n' => (int) ($config['n'] ?? 1), 'size' => $config['size'] ?? '1024x1024',
        ]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new RuntimeException('OpenAI image_generation failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
