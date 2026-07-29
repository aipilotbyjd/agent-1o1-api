<?php

namespace App\Services\Workflows\Nodes\Apps\Ai;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Facades\Http;

class LlmNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'llm';
    }

    public function name(): string
    {
        return 'LLM';
    }

    public function description(): string
    {
        return 'Generate text using AI models (Anthropic, OpenAI, Gemini, and more).';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'cpu';
    }

    public function color(): string
    {
        return '#8b5cf6';
    }

    public function credentialType(): ?string
    {
        return null;
    }

    public function docsUrl(): ?string
    {
        return null;
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'provider' => ['type' => 'string', 'enum' => ['anthropic', 'openai', 'gemini', 'azure', 'cohere', 'ollama', 'groq', 'mistral', 'deepseek', 'xai', 'openrouter'], 'default' => 'anthropic'],
            'model' => ['type' => 'string'],
            'prompt' => ['type' => 'string'],
            'system_prompt' => ['type' => 'string'],
            'max_tokens' => ['type' => 'integer', 'default' => 4096],
            'operation' => ['type' => 'string', 'enum' => ['generate', 'classify', 'summarize', 'sentiment', 'extract'], 'default' => 'generate'],
            'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
            'fields' => ['type' => 'array', 'items' => ['type' => 'string']],
        ], 'required' => ['prompt']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'response' => ['type' => 'string'],
            'usage' => ['type' => 'object'],
            'model' => ['type' => 'string'],
        ]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = $config['operation'] ?? 'generate';
        $prompt = $config['prompt'] ?? '';
        $systemOverride = null;

        return match ($operation) {
            'classify' => $this->generate($run, $config, "Classify the user's text into exactly one of these categories: ".implode(', ', (array) ($config['categories'] ?? [])).'. Respond with only the category name.'),
            'summarize' => $this->generate($run, $config, 'Summarize the following text concisely.'),
            'sentiment' => $this->generate($run, $config, 'Analyze the sentiment of the text. Respond with only: positive, negative, or neutral.'),
            'extract' => $this->generate($run, $config, 'Extract the following fields from the text and respond with only valid JSON: '.json_encode($config['fields'] ?? [])),
            default => $this->generate($run, $config, $config['system_prompt'] ?? null),
        };
    }

    private function generate(Run $run, array $config, ?string $systemOverride): array
    {
        $provider = $config['provider'] ?? 'anthropic';

        return match ($provider) {
            'anthropic' => $this->anthropic($run, $config, $systemOverride),
            'openai' => $this->openai($run, $config, $systemOverride),
            'gemini' => $this->gemini($run, $config, $systemOverride),
            'azure' => $this->azure($run, $config, $systemOverride),
            'cohere' => $this->cohere($run, $config, $systemOverride),
            'ollama' => $this->ollama($run, $config, $systemOverride),
            'groq' => $this->openaiCompatible($run, $config, $systemOverride, 'Groq', 'https://api.groq.com/openai/v1', 'llama-3.3-70b-versatile'),
            'mistral' => $this->openaiCompatible($run, $config, $systemOverride, 'Mistral', 'https://api.mistral.ai/v1', 'mistral-small-latest'),
            'deepseek' => $this->openaiCompatible($run, $config, $systemOverride, 'DeepSeek', 'https://api.deepseek.com/v1', 'deepseek-chat'),
            'xai' => $this->openaiCompatible($run, $config, $systemOverride, 'xAI', 'https://api.x.ai/v1', 'grok-3-mini'),
            'openrouter' => $this->openaiCompatible($run, $config, $systemOverride, 'OpenRouter', 'https://openrouter.ai/api/v1', 'openai/gpt-4o-mini'),
            default => throw new \RuntimeException("LLM: unknown provider '{$provider}'"),
        };
    }

    private function anthropic(Run $run, array $config, ?string $systemOverride): array
    {
        $apiKey = env('ANTHROPIC_API_KEY', '');
        $response = Http::acceptJson()->asJson()
            ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', array_filter([
                'model' => $config['model'] ?? 'claude-sonnet-5',
                'max_tokens' => (int) ($config['max_tokens'] ?? 4096),
                'system' => $systemOverride,
                'messages' => [['role' => 'user', 'content' => $config['prompt'] ?? '']],
            ]));

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API error: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['content'][0]['text'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $data['model'] ?? ''];
    }

    private function openai(Run $run, array $config, ?string $systemOverride): array
    {
        $apiKey = env('OPENAI_API_KEY', '');
        $messages = [];
        if ($system = $systemOverride) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $config['prompt'] ?? ''];
        $response = Http::acceptJson()->asJson()->withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', ['model' => $config['model'] ?? 'gpt-4o-mini', 'messages' => $messages]);
        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['choices'][0]['message']['content'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $data['model'] ?? ''];
    }

    private function gemini(Run $run, array $config, ?string $systemOverride): array
    {
        $apiKey = env('GEMINI_API_KEY', '');
        $model = $config['model'] ?? 'gemini-2.0-flash';
        $body = ['contents' => [['role' => 'user', 'parts' => [['text' => $config['prompt'] ?? '']]]]];
        if ($system = $systemOverride) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if ($maxTokens = $config['max_tokens'] ?? null) {
            $body['generationConfig'] = ['maxOutputTokens' => (int) $maxTokens];
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.$apiKey;
        $response = Http::acceptJson()->asJson()->post($url, $body);
        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API error: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '', 'usage' => ['input_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0, 'output_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0], 'model' => $model];
    }

    private function azure(Run $run, array $config, ?string $systemOverride): array
    {
        $apiKey = env('AZURE_OPENAI_API_KEY', '');
        $endpoint = rtrim(env('AZURE_OPENAI_URL', ''), '/');
        $deployment = env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o');
        $apiVersion = env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview');
        $messages = [];
        if ($system = $systemOverride) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $config['prompt'] ?? ''];
        $response = Http::acceptJson()->asJson()->withHeaders(['api-key' => $apiKey])->post("{$endpoint}/openai/deployments/{$deployment}/chat/completions?api-version={$apiVersion}", ['messages' => $messages]);
        if (! $response->successful()) {
            throw new \RuntimeException('Azure OpenAI API error: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['choices'][0]['message']['content'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $deployment];
    }

    private function cohere(Run $run, array $config, ?string $systemOverride): array
    {
        $apiKey = env('COHERE_API_KEY', '');
        $messages = [];
        if ($system = $systemOverride) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $config['prompt'] ?? ''];
        $response = Http::acceptJson()->asJson()->withToken($apiKey)->post('https://api.cohere.com/v2/chat', ['model' => $config['model'] ?? 'command-r-plus', 'messages' => $messages]);
        if (! $response->successful()) {
            throw new \RuntimeException('Cohere API error: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['message']['content'][0]['text'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $data['id'] ?? ''];
    }

    private function ollama(Run $run, array $config, ?string $systemOverride): array
    {
        $baseUrl = rtrim(env('OLLAMA_URL', 'http://localhost:11434'), '/').'/v1';
        $messages = [];
        if ($system = $systemOverride) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $config['prompt'] ?? ''];
        $response = Http::acceptJson()->asJson()->post("{$baseUrl}/chat/completions", ['model' => $config['model'] ?? 'llama3.2', 'messages' => $messages]);
        if (! $response->successful()) {
            throw new \RuntimeException('Ollama API error: '.$response->body());
        }
        $data = $response->json();

        return ['response' => $data['choices'][0]['message']['content'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $data['model'] ?? ''];
    }

    private function openaiCompatible(Run $run, array $config, ?string $systemOverride, string $name, string $baseUrl, string $defaultModel): array
    {
        $apiKey = env(strtoupper($name).'_API_KEY', '');
        $messages = [];
        if ($system = $systemOverride) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $config['prompt'] ?? ''];
        $response = Http::acceptJson()->asJson()->withToken($apiKey)->post("{$baseUrl}/chat/completions", ['model' => $config['model'] ?? $defaultModel, 'messages' => $messages]);
        if (! $response->successful()) {
            throw new \RuntimeException("{$name} API error: ".$response->body());
        }
        $data = $response->json();

        return ['response' => $data['choices'][0]['message']['content'] ?? '', 'usage' => $data['usage'] ?? [], 'model' => $data['model'] ?? ''];
    }
}
