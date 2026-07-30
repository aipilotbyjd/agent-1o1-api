<?php

namespace App\Services\Workflows\Nodes\Apps;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The base for connector nodes that talk to an HTTP API.
 *
 * Every one of them was repeating the same six lines around its actual request — start a
 * timer, build a bearer-token client with the same 15 second budget, check the response,
 * record the connector metric, throw on failure. That is all here now, so a connector is
 * left holding only what makes it that connector: its schema, its endpoint, and the
 * shape of its payload.
 *
 * Connectors that authenticate differently override {@see self::client()}; connectors
 * whose API reports failure in a 200 body (Slack's `ok: false`, GraphQL's `errors`)
 * override {@see self::succeeded()}.
 */
abstract class HttpAppNode extends AppNode
{
    /**
     * Seconds a single request may take before it is abandoned.
     */
    protected function timeout(): int
    {
        return 15;
    }

    /**
     * The authenticated client this connector's requests go through.
     */
    protected function client(Credential $credential): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->withToken($credential->data['token'] ?? '');
    }

    /**
     * Send a request, record how it went, and hand back the decoded body.
     *
     * @param  Closure(PendingRequest): Response  $send
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the call did not succeed.
     */
    protected function send(Run $run, Credential $credential, Closure $send): array
    {
        $startedAt = microtime(true);

        $response = $send($this->client($credential));
        $succeeded = $this->succeeded($response);

        $this->recordMetric($run, $succeeded, $startedAt);

        if (! $succeeded) {
            throw new RuntimeException($this->failureMessage($response));
        }

        return $response->json() ?? [];
    }

    /**
     * Whether the response represents a successful call.
     */
    protected function succeeded(Response $response): bool
    {
        return $response->successful();
    }

    protected function failureMessage(Response $response): string
    {
        return "{$this->type()} failed: ".$response->body();
    }
}
