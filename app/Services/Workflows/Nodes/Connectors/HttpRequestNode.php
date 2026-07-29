<?php

namespace App\Services\Workflows\Nodes\Connectors;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Http\OutboundUrlGuard;
use App\Services\Runs\ConnectorMetricRecorder;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class HttpRequestNode extends NodeDefinition implements ExecutableNode
{
    public const MAX_RESPONSE_LENGTH = 8000;

    public function type(): string
    {
        return 'http.request';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'HTTP Request';
    }

    public function description(): string
    {
        return 'Call any HTTP endpoint and capture its status, headers, and decoded JSON body.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'globe';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                'url' => ['type' => 'string'],
                'method' => ['type' => 'string'],
                'headers' => ['type' => 'object'],
                'query' => ['type' => 'object'],
                'body' => ['type' => 'object'],
                'timeout' => ['type' => 'integer'],
                'credential_id' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
                'status' => ['type' => 'integer'],
                'headers' => ['type' => 'object'],
                'body' => ['type' => 'string'],
                'json' => ['type' => 'object'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $url = (string) ($config['url'] ?? '');
        $method = strtoupper((string) ($config['method'] ?? 'GET'));

        app(OutboundUrlGuard::class)->assertAllowed($url);

        $request = Http::timeout((int) ($config['timeout'] ?? 15))
            ->withHeaders($config['headers'] ?? []);

        $credential = $this->credentialFor($run, $config);

        if ($credential !== null) {
            $request = self::applyCredential($request, $credential);
        }

        $startedAt = microtime(true);

        try {
            $response = $method === 'GET'
                ? $request->get($url, $config['query'] ?? [])
                : $request->send($method, $url, ['json' => $config['body'] ?? []]);
        } catch (Throwable $exception) {
            $this->recordMetric($run, false, $startedAt);

            throw $exception;
        }

        $this->recordMetric($run, $response->successful(), $startedAt);

        return self::formatResponse($response->status(), $response->headers(), $response->body(), $response->successful());
    }

    /**
     * Shared response shape, so every HTTP-backed connector returns the same keys.
     *
     * @param  array<string, array<int, string>>  $headers
     * @return array{ok: bool, status: int, headers: array<string, string>, body: string, json: mixed}
     */
    public static function formatResponse(int $status, array $headers, string $body, bool $ok): array
    {
        return [
            'ok' => $ok,
            'status' => $status,
            'headers' => array_map(fn (array $values): string => implode(', ', $values), $headers),
            'body' => Str::limit($body, self::MAX_RESPONSE_LENGTH),
            'json' => self::decode($body),
        ];
    }

    public static function decode(string $body): mixed
    {
        try {
            return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
    }

    public static function applyCredential(PendingRequest $request, Credential $credential): PendingRequest
    {
        if ($credential->isExpired()) {
            throw new InvalidArgumentException("Credential [{$credential->name}] has expired.");
        }

        $credential->touchLastUsed();
        $data = $credential->data ?? [];

        return match ($credential->type) {
            Credential::TYPE_BEARER_TOKEN => $request->withToken((string) ($data['token'] ?? '')),
            Credential::TYPE_BASIC_AUTH => $request->withBasicAuth(
                (string) ($data['username'] ?? ''),
                (string) ($data['password'] ?? ''),
            ),
            Credential::TYPE_API_KEY => $request->withHeaders([
                (string) ($data['header'] ?? 'Authorization') => (string) ($data['value'] ?? ''),
            ]),
            default => $request,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function credentialFor(Run $run, array $config): ?Credential
    {
        if (($config['credential_id'] ?? null) === null) {
            return null;
        }

        return Credential::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['credential_id']);
    }

    private function recordMetric(Run $run, bool $success, float $startedAt): void
    {
        app(ConnectorMetricRecorder::class)->record(
            $run->workspace_id,
            $this->type(),
            $success,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
