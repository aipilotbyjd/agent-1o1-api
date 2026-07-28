<?php

namespace App\Ai\Tools\Handlers;

use App\Models\Credentials\Credential;
use App\Models\Tool;
use App\Services\Runs\ConnectorMetricRecorder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class HttpToolHandler implements ToolHandler
{
    private const MAX_RESPONSE_LENGTH = 8000;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Tool $tool, array $arguments): string
    {
        $config = $tool->config ?? [];
        $method = strtoupper($config['method'] ?? 'GET');

        $request = Http::timeout((int) ($config['timeout'] ?? 15))
            ->withHeaders($config['headers'] ?? []);

        $request = $this->applyCredential($request, $tool);

        $startedAt = microtime(true);

        try {
            $response = $method === 'GET'
                ? $request->get($config['url'], $arguments)
                : $request->send($method, $config['url'], ['json' => $arguments]);
        } catch (Throwable $exception) {
            $this->recordMetric($tool, false, $startedAt);

            throw $exception;
        }

        $this->recordMetric($tool, $response->successful(), $startedAt);

        return Str::limit(
            "HTTP {$response->status()}\n".$response->body(),
            self::MAX_RESPONSE_LENGTH,
        );
    }

    private function recordMetric(Tool $tool, bool $success, float $startedAt): void
    {
        app(ConnectorMetricRecorder::class)->record(
            $tool->workspace_id,
            $tool->slug ?? $tool->name,
            $success,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    private function applyCredential(PendingRequest $request, Tool $tool): PendingRequest
    {
        $credential = $tool->credential;

        if ($credential === null) {
            return $request;
        }

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
}
