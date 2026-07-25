<?php

namespace App\Services\Workflows\Handlers;

use App\Ai\WorkspaceAgent;
use App\Models\Agent;
use App\Models\Run;
use App\Services\Workflows\TemplateResolver;
use InvalidArgumentException;

class AgentStepHandler implements StepHandler
{
    public function __construct(public TemplateResolver $templates) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>, usage: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];

        $agent = Agent::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['agent_id'] ?? null);

        if ($agent === null) {
            throw new InvalidArgumentException("Step [{$step['key']}] references a missing agent.");
        }

        $prompt = $this->templates->resolve($config['prompt'] ?? '{{ input.message }}', $context);

        $response = (new WorkspaceAgent($agent, $run))->prompt(
            $prompt,
            provider: $agent->provider,
            model: $agent->model,
        );

        return [
            'output' => ['text' => $response->text, 'agent_version' => $agent->currentVersionNumber()],
            'usage' => $response->usage->toArray(),
        ];
    }
}
