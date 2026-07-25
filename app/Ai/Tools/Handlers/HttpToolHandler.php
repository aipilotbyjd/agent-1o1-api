<?php

namespace App\Ai\Tools\Handlers;

use App\Models\Tool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

        $response = $method === 'GET'
            ? $request->get($config['url'], $arguments)
            : $request->send($method, $config['url'], ['json' => $arguments]);

        return Str::limit(
            "HTTP {$response->status()}\n".$response->body(),
            self::MAX_RESPONSE_LENGTH,
        );
    }
}
