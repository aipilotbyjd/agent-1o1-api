<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Models\Tool;
use App\Services\Http\OutboundUrlGuard;
use App\Services\Runs\ConnectorMetricRecorder;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Executes a saved `tools` row — a workspace-authored HTTP call with its own parameter
 * schema and credential. This is the one node that reads the tools table; every other
 * connector is defined in code, which is why tools rows are instances of this node
 * rather than a registry of their own.
 */
class CustomHttpNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'custom.http';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Custom Tool';
    }

    public function description(): string
    {
        return 'Call a tool saved in this workspace, with its stored URL and credential.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'wrench';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['tool_id'],
            'properties' => [
                'tool_id' => ['type' => 'integer'],
                'arguments' => ['type' => 'object'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return (new HttpRequestNode)->outputSchema();
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $tool = Tool::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['tool_id'] ?? null);

        if ($tool === null) {
            throw new InvalidArgumentException('This step references a tool that no longer exists.');
        }

        return $this->call($tool, $config['arguments'] ?? []);
    }

    /**
     * Run a tool row directly. Used by the agent-facing DynamicTool and the tool test
     * endpoint, neither of which has a Run to execute against.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(Tool $tool, array $arguments): array
    {
        $config = $tool->config ?? [];
        $method = strtoupper((string) ($config['method'] ?? 'GET'));
        $url = (string) ($config['url'] ?? '');

        app(OutboundUrlGuard::class)->assertAllowed($url);

        $request = Http::timeout((int) ($config['timeout'] ?? 15))
            ->withHeaders($config['headers'] ?? []);

        if ($tool->credential !== null) {
            $request = HttpRequestNode::applyCredential($request, $tool->credential);
        }

        $startedAt = microtime(true);

        try {
            $response = $method === 'GET'
                ? $request->get($url, $arguments)
                : $request->send($method, $url, ['json' => $arguments]);
        } catch (Throwable $exception) {
            $this->recordMetric($tool, false, $startedAt);

            throw $exception;
        }

        $this->recordMetric($tool, $response->successful(), $startedAt);

        return HttpRequestNode::formatResponse(
            $response->status(),
            $response->headers(),
            $response->body(),
            $response->successful(),
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
}
