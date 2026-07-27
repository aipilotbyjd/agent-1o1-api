<?php

namespace App\Services\Workflows\Handlers;

use App\Ai\Tools\ToolHandlerRegistry;
use App\Models\Runs\Run;
use App\Models\Tool;
use App\Services\Workflows\TemplateResolver;
use InvalidArgumentException;

class ToolStepHandler implements StepHandler
{
    public function __construct(
        public ToolHandlerRegistry $registry,
        public TemplateResolver $templates,
    ) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];

        $tool = Tool::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['tool_id'] ?? null);

        if ($tool === null) {
            throw new InvalidArgumentException("Step [{$step['key']}] references a missing tool.");
        }

        $arguments = $this->templates->resolveArray($config['arguments'] ?? [], $context);

        $result = $this->registry->for($tool)->execute($tool, $arguments);

        return ['output' => ['result' => $result]];
    }
}
