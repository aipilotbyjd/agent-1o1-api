<?php

namespace App\Services\Workflows\Nodes;

class StepNodeResolver
{
    public function __construct(private readonly NodeResolver $nodes) {}

    /**
     * Work out which node a graph step is really driven by.
     *
     * Most steps map straight from their step type. A `tool` step is the exception: it
     * names its connector in config (`node`), which may be a builtin definition or a
     * node this workspace authored.
     *
     * @param  array<string, mixed>  $step
     */
    public function typeFor(array $step): ?string
    {
        $config = $step['config'] ?? [];

        if (isset($config['node']) && $this->nodes->has((string) $config['node'])) {
            return (string) $config['node'];
        }

        $core = 'core.'.($step['type'] ?? '');

        return $this->nodes->has($core) ? $core : null;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function definitionFor(array $step): ?NodeDefinition
    {
        $type = $this->typeFor($step);

        return $type === null ? null : $this->nodes->definition($type);
    }
}
