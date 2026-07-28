<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;
use App\Services\Workflows\TemplateResolver;
use InvalidArgumentException;

class LoopStepHandler implements StepHandler
{
    public function __construct(public TemplateResolver $templates) {}

    /**
     * Map a template over every item in a context list, one output entry per item.
     * This is a single-step "foreach"; it does not fan out to other graph steps per
     * iteration — chain a Transform/Tool step after it to act on `results`.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $items = data_get($context, (string) ($config['items'] ?? ''));

        if (! is_array($items)) {
            throw new InvalidArgumentException("Step [{$step['key']}] items path did not resolve to a list.");
        }

        $mapping = $config['mapping'] ?? [];
        $results = [];

        foreach (array_values($items) as $index => $item) {
            $itemContext = [...$context, 'item' => $item, 'index' => $index];
            $results[] = $this->templates->resolveArray($mapping, $itemContext);
        }

        return ['output' => ['results' => $results, 'count' => count($results)]];
    }
}
