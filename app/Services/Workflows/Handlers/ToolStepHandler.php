<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\NodeRegistry;
use App\Services\Workflows\TemplateResolver;
use InvalidArgumentException;

class ToolStepHandler implements StepHandler
{
    public function __construct(
        public NodeRegistry $registry,
        public TemplateResolver $templates,
    ) {}

    /**
     * Run a `tool` step through whichever connector its config names.
     *
     * A step either names a registry node directly (`node: "slack.post_message"`) or
     * carries a legacy `tool_id`, which is the saved-tool form of the custom.http node.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $type = $config['node'] ?? (isset($config['tool_id']) ? 'custom.http' : null);

        if ($type === null) {
            throw new InvalidArgumentException(
                "Step [{$step['key']}] does not name a node or a tool to run.",
            );
        }

        $node = $this->registry->executable((string) $type);

        // Engine-level keys are stripped so a connector only sees its own config.
        $resolved = $this->templates->resolveArray(
            array_diff_key($config, array_flip(['node', 'max_attempts', 'retry_delay_seconds', 'timeout_seconds', 'continue_on_error'])),
            $context,
        );

        // The connector's structured result *is* the step output, so a later step can
        // read into it directly (`steps.fetch.json.items.0.id`).
        return ['output' => $node->execute($run, $resolved, $context)];
    }
}
