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
        $steps = $graph['steps'] ?? [];
        $edges = $graph['edges'] ?? [];

        $issues = [
            ...$this->duplicateKeyIssues($steps),
            ...$this->danglingEdgeIssues($steps, $edges),
        ];

        // A graph with dangling edges cannot be traversed reliably, so reachability and
        // cycle detection would only produce noise on top of the real problem.
        if ($issues !== []) {
            return $issues;
        }

        return [
            ...$this->cycleIssues($steps, $edges),
            ...$this->entryIssues($steps, $edges),
            ...$this->reachabilityIssues($steps, $edges),
            ...$this->configIssues($steps),
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
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, string>
     */
    private function duplicateKeyIssues(array $steps): array
    {
        $counts = array_count_values(array_column($steps, 'key'));

        return array_values(array_map(
            fn (string $key): string => "Step key [{$key}] is used more than once.",
            array_keys(array_filter($counts, fn (int $count): bool => $count > 1)),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function danglingEdgeIssues(array $steps, array $edges): array
    {
        $keys = array_column($steps, 'key');
        $issues = [];

        foreach ($edges as $edge) {
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
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function cycleIssues(array $steps, array $edges): array
    {
        $adjacency = $this->adjacency($edges);
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

        foreach (array_column($steps, 'key') as $key) {
            if (($state[$key] ?? null) === null) {
                $visit($key);
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function entryIssues(array $steps, array $edges): array
    {
        if ($steps === []) {
            return ['The graph has no steps.'];
        }

        return $this->entryKeys($steps, $edges) === []
            ? ['The graph has no entry step — every step has an incoming edge.']
            : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function reachabilityIssues(array $steps, array $edges): array
    {
        $entries = $this->entryKeys($steps, $edges);

        if ($entries === []) {
            return [];
        }

        $adjacency = $this->adjacency($edges);
        $reached = [];
        $queue = $entries;

        while ($queue !== []) {
            $key = array_shift($queue);

            if (isset($reached[$key])) {
                continue;
            }

            $reached[$key] = true;

            foreach ($adjacency[$key] ?? [] as $next) {
                $queue[] = $next;
            }
        }

        return array_values(array_map(
            fn (string $key): string => "Step [{$key}] is unreachable from any entry step.",
            array_filter(
                array_column($steps, 'key'),
                fn (string $key): bool => ! isset($reached[$key]),
            ),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, string>
     */
    private function configIssues(array $steps): array
    {
        $issues = [];

        foreach ($steps as $step) {
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

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    private function entryKeys(array $steps, array $edges): array
    {
        $targets = array_column($edges, 'to');

        return array_values(array_filter(
            array_column($steps, 'key'),
            fn (string $key): bool => ! in_array($key, $targets, true),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array<int, string>>
     */
    private function adjacency(array $edges): array
    {
        $adjacency = [];

        foreach ($edges as $edge) {
            $adjacency[$edge['from']][] = $edge['to'];
        }

        return $adjacency;
    }
}
