<?php

use App\Services\Workflows\WorkflowGraph;

function graph(array $steps, array $edges = []): WorkflowGraph
{
    return WorkflowGraph::fromArray([
        'steps' => array_map(fn (string $key): array => ['key' => $key, 'type' => 'transform'], $steps),
        'edges' => $edges,
    ]);
}

it('treats a null or empty graph as empty', function () {
    expect(WorkflowGraph::fromArray(null)->isEmpty())->toBeTrue()
        ->and(WorkflowGraph::fromArray([])->isEmpty())->toBeTrue()
        ->and(WorkflowGraph::fromArray([])->entrySteps())->toBe([])
        ->and(WorkflowGraph::fromArray([])->topologicalOrder())->toBe([]);
});

it('finds entry steps as those with no incoming edge', function () {
    $g = graph(['a', 'b', 'c'], [
        ['from' => 'a', 'to' => 'b'],
        ['from' => 'b', 'to' => 'c'],
    ]);

    expect($g->entryKeys())->toBe(['a']);
});

it('reports every entry step when branches start in parallel', function () {
    $g = graph(['a', 'b', 'join'], [
        ['from' => 'a', 'to' => 'join'],
        ['from' => 'b', 'to' => 'join'],
    ]);

    expect($g->entryKeys())->toBe(['a', 'b'])
        ->and($g->predecessorKeys('join'))->toBe(['a', 'b']);
});

it('deduplicates predecessors and successors across parallel edges', function () {
    $g = graph(['a', 'b'], [
        ['from' => 'a', 'to' => 'b', 'condition' => 'true'],
        ['from' => 'a', 'to' => 'b', 'condition' => 'false'],
    ]);

    expect($g->predecessorKeys('b'))->toBe(['a'])
        ->and($g->successorKeys('a'))->toBe(['b'])
        ->and($g->outgoingFrom('a'))->toHaveCount(2);
});

it('looks up a step and an edge by key', function () {
    $g = graph(['a', 'b'], [['from' => 'a', 'to' => 'b', 'condition' => 'error']]);

    expect($g->step('a')['type'])->toBe('transform')
        ->and($g->step('missing'))->toBeNull()
        ->and($g->edgeBetween('a', 'b')['condition'])->toBe('error')
        ->and($g->edgeBetween('b', 'a'))->toBeNull();
});

it('detects an outgoing edge carrying a given condition', function () {
    $g = graph(['a', 'b', 'c'], [
        ['from' => 'a', 'to' => 'b'],
        ['from' => 'a', 'to' => 'c', 'condition' => 'error'],
    ]);

    expect($g->hasOutgoingCondition('a', 'error'))->toBeTrue()
        ->and($g->hasOutgoingCondition('b', 'error'))->toBeFalse();
});

it('orders steps topologically', function () {
    $g = graph(['c', 'a', 'b'], [
        ['from' => 'a', 'to' => 'b'],
        ['from' => 'b', 'to' => 'c'],
    ]);

    expect(array_column($g->topologicalOrder(), 'key'))->toBe(['a', 'b', 'c']);
});

it('drops steps trapped in a cycle rather than looping forever', function () {
    $g = graph(['a', 'b', 'c'], [
        ['from' => 'a', 'to' => 'b'],
        ['from' => 'b', 'to' => 'c'],
        ['from' => 'c', 'to' => 'b'],
    ]);

    expect(array_column($g->topologicalOrder(), 'key'))->toBe(['a']);
});

it('preserves duplicate step keys so the validator can report them', function () {
    $g = graph(['a', 'a', 'b']);

    expect($g->stepKeys())->toBe(['a', 'a', 'b']);
});

it('survives edges pointing at steps that do not exist', function () {
    $g = graph(['a'], [['from' => 'a', 'to' => 'ghost']]);

    expect($g->successorKeys('a'))->toBe(['ghost'])
        ->and($g->step('ghost'))->toBeNull()
        ->and(array_column($g->topologicalOrder(), 'key'))->toBe(['a']);
});
