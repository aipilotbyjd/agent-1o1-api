<?php

namespace App\Services\Workflows\Nodes\Custom;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Core\HttpRequestNode;

/**
 * A workspace-authored node, backed by a row rather than a class.
 *
 * The builtin catalog is defined in code; a custom node is the same thing defined by a
 * user, so it presents itself from its row and executes as an HTTP call whose url,
 * method, and credential were fixed when the node was saved. Its `config_schema` fields
 * are the call's arguments, which is what lets the very same row be handed to an agent
 * as a tool.
 *
 * Extending HttpRequestNode rather than re-sending the request means there is one
 * outbound HTTP path in the engine — and because the parent records its metric against
 * `$this->type()`, calls are still attributed to this node and not to `http.request`.
 */
class CustomNode extends HttpRequestNode
{
    public function __construct(public readonly Node $node) {}

    public function type(): string
    {
        return $this->node->type;
    }

    public function stepType(): WorkflowStepType
    {
        return $this->node->step_type;
    }

    public function name(): string
    {
        return $this->node->name;
    }

    public function description(): string
    {
        return (string) $this->node->description;
    }

    public function category(): string
    {
        return $this->node->category?->slug ?? 'actions';
    }

    public function icon(): string
    {
        return $this->node->icon;
    }

    public function color(): string
    {
        return $this->node->color;
    }

    public function version(): int
    {
        return $this->node->version;
    }

    public function credentialType(): ?string
    {
        return $this->node->credential_type;
    }

    public function isPremium(): bool
    {
        return $this->node->is_premium;
    }

    public function docsUrl(): ?string
    {
        return $this->node->docs_url;
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return $this->node->config_schema ?? ['type' => 'object', 'properties' => []];
    }

    /**
     * @param  array<string, mixed>  $config  The node's own fields, acting as call arguments.
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array
    {
        return parent::execute($run, $this->requestConfigFor($config), $context);
    }

    /**
     * Fold the node's stored call definition together with this invocation's arguments
     * into the config shape HttpRequestNode expects.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function requestConfigFor(array $arguments): array
    {
        $definition = $this->node->config ?? [];
        $method = strtoupper((string) ($definition['method'] ?? 'GET'));

        return [
            'url' => (string) ($definition['url'] ?? ''),
            'method' => $method,
            'headers' => $definition['headers'] ?? [],
            'timeout' => $definition['timeout'] ?? 15,
            'credential_id' => $this->node->credential_id,
            // A GET carries its arguments in the querystring, everything else in a JSON
            // body — the same split the retired tools table used.
            ($method === 'GET' ? 'query' : 'body') => $arguments,
        ];
    }
}
