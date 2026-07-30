<?php

namespace App\Services\Workflows;

/**
 * A published workflow's shape: its steps and the edges between them.
 *
 * The engine, the validator, and the dry runner all used to walk the raw
 * `['steps' => [], 'edges' => []]` array with their own private helpers, which meant
 * three separate answers to "what are the entry steps" and a `?? []` on every access.
 * This owns those questions instead.
 *
 * Deliberately tolerant of malformed input — duplicate step keys and edges pointing at
 * nothing are exactly what {@see GraphValidator} exists to report, so they have to
 * survive being loaded rather than blowing up here.
 */
final class WorkflowGraph
{
    /**
     * @var array<string, array<int, string>>|null
     */
    private ?array $adjacency = null;

    /**
     * @var array<string, array<int, string>>|null
     */
    private ?array $reverseAdjacency = null;

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function __construct(
        private readonly array $steps,
        private readonly array $edges,
    ) {}

    /**
     * @param  array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}|null  $graph
     */
    public static function fromArray(?array $graph): self
    {
        return new self(
            array_values($graph['steps'] ?? []),
            array_values($graph['edges'] ?? []),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function edges(): array
    {
        return $this->edges;
    }

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }

    /**
     * Every step key in declaration order. Duplicates are preserved — the validator
     * reports them, so collapsing them here would hide the problem.
     *
     * @return array<int, string>
     */
    public function stepKeys(): array
    {
        return array_column($this->steps, 'key');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function step(string $key): ?array
    {
        foreach ($this->steps as $step) {
            if (($step['key'] ?? null) === $key) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Steps with no incoming edge — where execution begins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entrySteps(): array
    {
        $targets = array_column($this->edges, 'to');

        return array_values(array_filter(
            $this->steps,
            fn (array $step): bool => ! in_array($step['key'] ?? null, $targets, true),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function entryKeys(): array
    {
        return array_column($this->entrySteps(), 'key');
    }

    /**
     * The distinct step keys with an edge pointing at the given step.
     *
     * @return array<int, string>
     */
    public function predecessorKeys(string $key): array
    {
        return $this->reverseAdjacency()[$key] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function successorKeys(string $key): array
    {
        return $this->adjacency()[$key] ?? [];
    }

    /**
     * Every edge leaving the given step, in declaration order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outgoingFrom(string $key): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (array $edge): bool => ($edge['from'] ?? null) === $key,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function edgeBetween(string $from, string $to): ?array
    {
        foreach ($this->edges as $edge) {
            if (($edge['from'] ?? null) === $from && ($edge['to'] ?? null) === $to) {
                return $edge;
            }
        }

        return null;
    }

    /**
     * Whether any edge leaving the step carries the given condition.
     */
    public function hasOutgoingCondition(string $key, string $condition): bool
    {
        foreach ($this->outgoingFrom($key) as $edge) {
            if (($edge['condition'] ?? null) === $condition) {
                return true;
            }
        }

        return false;
    }

    /**
     * Step keys mapped to the keys they point at.
     *
     * @return array<string, array<int, string>>
     */
    public function adjacency(): array
    {
        if ($this->adjacency !== null) {
            return $this->adjacency;
        }

        $adjacency = [];

        foreach ($this->edges as $edge) {
            $adjacency[$edge['from'] ?? ''][] = $edge['to'] ?? '';
        }

        return $this->adjacency = array_map(
            fn (array $targets): array => array_values(array_unique($targets)),
            $adjacency,
        );
    }

    /**
     * Steps in the order the engine would reach them.
     *
     * A cyclic graph cannot be fully ordered, so any step still carrying incoming edges
     * when the queue drains is simply left out — callers run this only after the
     * validator has ruled cycles out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topologicalOrder(): array
    {
        $incoming = array_fill_keys($this->stepKeys(), 0);

        foreach ($this->edges as $edge) {
            $to = $edge['to'] ?? null;

            if ($to !== null && array_key_exists($to, $incoming)) {
                $incoming[$to]++;
            }
        }

        $queue = array_keys($incoming, 0, true);
        $ordered = [];

        while ($queue !== []) {
            $key = array_shift($queue);
            $step = $this->step($key);

            if ($step === null) {
                continue;
            }

            $ordered[] = $step;

            foreach ($this->successorKeys($key) as $next) {
                if (! array_key_exists($next, $incoming)) {
                    continue;
                }

                if (--$incoming[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function reverseAdjacency(): array
    {
        if ($this->reverseAdjacency !== null) {
            return $this->reverseAdjacency;
        }

        $reverse = [];

        foreach ($this->edges as $edge) {
            $reverse[$edge['to'] ?? ''][] = $edge['from'] ?? '';
        }

        return $this->reverseAdjacency = array_map(
            fn (array $sources): array => array_values(array_unique($sources)),
            $reverse,
        );
    }
}
