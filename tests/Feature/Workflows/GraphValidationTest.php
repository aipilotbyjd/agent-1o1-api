<?php

use App\Exceptions\Workflows\InvalidGraphException;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\GraphValidator;

function validationWorkflow(): Workflow
{
    return Workflow::factory()->create(['workspace_id' => Workspace::factory()]);
}

it('accepts a linear graph', function () {
    $workflow = validationWorkflow();

    $a = $workflow->steps()->create(['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]);
    $b = $workflow->steps()->create(['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]]);
    $workflow->edges()->create(['from_step_id' => $a->id, 'to_step_id' => $b->id]);

    expect(app(GraphValidator::class)->issues($workflow->graphArray()))->toBe([]);
});

it('rejects a graph containing a cycle', function () {
    $graph = [
        'steps' => [
            ['key' => 'a', 'type' => 'transform', 'config' => []],
            ['key' => 'b', 'type' => 'transform', 'config' => []],
            ['key' => 'c', 'type' => 'transform', 'config' => []],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'b', 'to' => 'c'],
            ['from' => 'c', 'to' => 'a'],
        ],
    ];

    expect(app(GraphValidator::class)->issues($graph))
        ->toContain('The graph contains a cycle involving step [a].');
});

it('rejects an edge pointing at a step that does not exist', function () {
    $graph = [
        'steps' => [['key' => 'a', 'type' => 'transform', 'config' => []]],
        'edges' => [['from' => 'a', 'to' => 'ghost']],
    ];

    expect(app(GraphValidator::class)->issues($graph))
        ->toContain('Edge [a -> ghost] points at a step that does not exist.');
});

it('rejects a graph with no entry step', function () {
    $graph = [
        'steps' => [
            ['key' => 'a', 'type' => 'transform', 'config' => []],
            ['key' => 'b', 'type' => 'transform', 'config' => []],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'b', 'to' => 'a'],
        ],
    ];

    expect(app(GraphValidator::class)->issues($graph))
        ->toContain('The graph has no entry step — every step has an incoming edge.');
});

it('reports a step that cannot be reached from any entry step', function () {
    $graph = [
        'steps' => [
            ['key' => 'a', 'type' => 'transform', 'config' => []],
            ['key' => 'b', 'type' => 'transform', 'config' => []],
            ['key' => 'orphan_from', 'type' => 'transform', 'config' => []],
            ['key' => 'orphan_to', 'type' => 'transform', 'config' => []],
        ],
        'edges' => [
            ['from' => 'a', 'to' => 'b'],
            ['from' => 'orphan_from', 'to' => 'orphan_to'],
            ['from' => 'orphan_to', 'to' => 'orphan_from'],
        ],
    ];

    $issues = app(GraphValidator::class)->issues($graph);

    expect($issues)->toContain('Step [orphan_from] is unreachable from any entry step.')
        ->and($issues)->toContain('Step [orphan_to] is unreachable from any entry step.');
});

it('rejects duplicate step keys', function () {
    $graph = [
        'steps' => [
            ['key' => 'a', 'type' => 'transform', 'config' => []],
            ['key' => 'a', 'type' => 'transform', 'config' => []],
        ],
        'edges' => [],
    ];

    expect(app(GraphValidator::class)->issues($graph))
        ->toContain('Step key [a] is used more than once.');
});

it('blocks publishing a workflow whose graph is invalid', function () {
    $workflow = validationWorkflow();

    $a = $workflow->steps()->create(['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]);
    $b = $workflow->steps()->create(['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => []]]);
    $workflow->edges()->create(['from_step_id' => $a->id, 'to_step_id' => $b->id]);
    $workflow->edges()->create(['from_step_id' => $b->id, 'to_step_id' => $a->id]);

    expect(fn () => $workflow->publishVersion())->toThrow(InvalidGraphException::class);

    expect($workflow->fresh()->versions()->count())->toBe(0);
});
