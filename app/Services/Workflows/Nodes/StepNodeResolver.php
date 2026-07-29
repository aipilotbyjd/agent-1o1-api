<?php

namespace App\Services\Workflows\Nodes;

class StepNodeResolver
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * Work out which registry node a graph step is really driven by.
     *
     * Most steps map straight from their step type. A `tool` step is the exception: it
     * names its connector in config (`node`), or carries a legacy `tool_id`, which is
     * the saved-tool form of custom.http.
     *
     * @param  array<string, mixed>  $step
     */
    public function typeFor(array $step): ?string
    {
        $config = $step['config'] ?? [];

        if (isset($config['node']) && $this->registry->has((string) $config['node'])) {
            return (string) $config['node'];
        }

        if (($step['type'] ?? null) === 'tool' && isset($config['tool_id'])) {
            return 'custom.http';
        }

        $core = 'core.'.($step['type'] ?? '');

        return $this->registry->has($core) ? $core : null;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function definitionFor(array $step): ?NodeDefinition
    {
        $type = $this->typeFor($step);

        return $type === null ? null : $this->registry->get($type);
    }
}
