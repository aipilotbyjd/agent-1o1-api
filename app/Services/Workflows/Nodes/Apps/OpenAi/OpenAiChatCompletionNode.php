<?php

namespace App\Services\Workflows\Nodes\Apps\OpenAi;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class OpenAiChatCompletionNode extends AppNode
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function type(): string
    {
        return 'openai.chat_completion';
    }

    public function name(): string
    {
        return 'OpenAI: Chat Completion';
    }

    public function description(): string
    {
        return 'Generate a chat completion using OpenAI.';
    }

    public function icon(): string
    {
        return 'message-circle';
    }

    public function color(): string
    {
        return '#000000';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://platform.openai.com/docs/api-reference/chat';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'prompt'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'prompt' => ['type' => 'string'], 'model' => ['type' => 'string'], 'max_tokens' => ['type' => 'integer'], 'temperature' => ['type' => 'number']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['response' => ['type' => 'string'], 'usage' => ['type' => 'object']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $response = Http::timeout(30)->withToken($credential->data['api_key'] ?? '')->post(self::BASE_URL.'/chat/completions', [
            'model' => $config['model'] ?? 'gpt-4o-mini', 'messages' => [['role' => 'user', 'content' => $config['prompt']]],
            'max_tokens' => $config['max_tokens'] ?? null, 'temperature' => $config['temperature'] ?? null,
        ]);
        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);
        if (! $ok) {
            throw new \RuntimeException('OpenAI chat_completion failed: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['choices'][0]['message']['content'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $data['model'] ?? ''];
    }
}
