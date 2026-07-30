<?php

namespace App\Services\Workflows;

use App\Services\Workflows\Nodes\ConfigSchemaValidator;
use App\Services\Workflows\Nodes\StepNodeResolver;

class GraphValidator
{
    public function __construct(
        private readonly StepNodeResolver $resolver,
        private readonly ConfigSchemaValidator $configs,
    ) {}

    /**
     * Every structural problem with a graph, as human-readable sentences.
     *
     * The engine walks a graph by dispatching a job per step, so an invalid shape is not
     * a crash — it is a run that hangs, silently truncates, or never starts. Catching it
     * at publish time is the only place the author still has context to fix it.
     *
     * @param  array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     * @return array<int, string>
     */
    public function issues(array $graph): array
    {
        $graph = WorkflowGraph::fromArray($graph);

        $issues = [
            ...$this->duplicateKeyIssues($graph),
            ...$this->danglingEdgeIssues($graph),
        ];

        // A graph with dangling edges cannot be traversed reliably, so reachability and
        // cycle detection would only produce noise on top of the real problem.
        if ($issues !== []) {
            return $issues;
        }

        return [
            ...$this->cycleIssues($graph),
            ...$this->entryIssues($graph),
            ...$this->reachabilityIssues($graph),
            ...$this->configIssues($graph),
        ];
    }

    /**
     * @param  array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     */
    public function isValid(array $graph): bool
    {
        return $this->issues($graph) === [];
    }

    /**
     * @return array<int, string>
     */
    private function duplicateKeyIssues(WorkflowGraph $graph): array
    {
        $counts = array_count_values($graph->stepKeys());

        return array_values(array_map(
            fn (string $key): string => "Step key [{$key}] is used more than once.",
            array_keys(array_filter($counts, fn (int $count): bool => $count > 1)),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function danglingEdgeIssues(WorkflowGraph $graph): array
    {
        $keys = $graph->stepKeys();
        $issues = [];

        foreach ($graph->edges() as $edge) {
            foreach (['from', 'to'] as $end) {
                if (! in_array($edge[$end] ?? null, $keys, true)) {
                    $issues[] = "Edge [{$edge['from']} -> {$edge['to']}] points at a step that does not exist.";

                    continue 2;
                }
            }
        }

        return $issues;
    }

    /**
     * Depth-first search, reporting the first step found on each back edge.
     *
     * @return array<int, string>
     */
    private function cycleIssues(WorkflowGraph $graph): array
    {
        $adjacency = $graph->adjacency();
        $state = [];
        $issues = [];

        $visit = function (string $key) use (&$visit, &$state, &$issues, $adjacency): void {
            $state[$key] = 'visiting';

            foreach ($adjacency[$key] ?? [] as $next) {
                if (($state[$next] ?? null) === 'visiting') {
                    $issues[] = "The graph contains a cycle involving step [{$next}].";

                    continue;
                }

                if (($state[$next] ?? null) === null) {
                    $visit($next);
                }
            }

            $state[$key] = 'visited';
        };

        foreach ($graph->stepKeys() as $key) {
            if (($state[$key] ?? null) === null) {
                $visit($key);
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<int, string>
     */
    private function entryIssues(WorkflowGraph $graph): array
    {
        if ($graph->isEmpty()) {
            return ['The graph has no steps.'];
        }

        return $graph->entryKeys() === []
            ? ['The graph has no entry step — every step has an incoming edge.']
            : [];
    }

    /**
     * @return array<int, string>
     */
    private function reachabilityIssues(WorkflowGraph $graph): array
    {
        $queue = $graph->entryKeys();

        if ($queue === []) {
            return [];
        }

        $reached = [];

        while ($queue !== []) {
            $key = array_shift($queue);

            if (isset($reached[$key])) {
                continue;
            }

            $reached[$key] = true;

            foreach ($graph->successorKeys($key) as $next) {
                $queue[] = $next;
            }
        }

        return array_values(array_map(
            fn (string $key): string => "Step [{$key}] is unreachable from any entry step.",
            array_filter(
                $graph->stepKeys(),
                fn (string $key): bool => ! isset($reached[$key]),
            ),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function configIssues(WorkflowGraph $graph): array
    {
        $issues = [];

        foreach ($graph->steps() as $step) {
            $definition = $this->resolver->definitionFor($step);

            if ($definition === null) {
                $issues[] = "Step [{$step['key']}] has no matching node for type [{$step['type']}].";

                continue;
            }

            $issues = [...$issues, ...$this->configs->issues(
                $definition->configSchema(),
                $step['config'] ?? [],
                (string) $step['key'],
            )];
        }

        return $issues;
    }
}
