<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\NodeResolver;
use App\Services\Workflows\StepOptions;
use App\Services\Workflows\TemplateResolver;
use InvalidArgumentException;

class ToolStepHandler implements StepHandler
{
    public function __construct(
        public NodeResolver $nodes,
        public TemplateResolver $templates,
    ) {}

    /**
     * Run a `tool` step through whichever connector its config names.
     *
     * The step names its node in config (`node: "slack.post_message"`), which the
     * resolver looks up in the code registry or, for a workspace-authored node, in the
     * nodes table.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $type = $config['node'] ?? null;

        if ($type === null) {
            throw new InvalidArgumentException(
                "Step [{$step['key']}] does not name a node to run.",
            );
        }

        $node = $this->nodes->executable((string) $type);

        // Engine-level keys are stripped so a connector only sees its own config.
        $resolved = $this->templates->resolveArray(
            StepOptions::stripReserved($config),
            $context,
        );

        // The connector's structured result *is* the step output, so a later step can
        // read into it directly (`steps.fetch.json.items.0.id`).
        return ['output' => $node->execute($run, $resolved, $context)];
    }
}
