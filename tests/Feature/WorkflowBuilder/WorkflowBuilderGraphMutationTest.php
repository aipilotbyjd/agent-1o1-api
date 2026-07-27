<?php

use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\RemoveNodeTool;
use App\Models\Workflows\WorkflowBuilderSession;
use Laravel\Ai\Tools\Request as ToolRequest;

it('adds a step to the draft graph and snapshots a draft version', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $session->addStep('s1', 'delay', ['seconds' => 5]);

    expect($session->draft_graph['steps'])->toHaveCount(1)
        ->and($session->draft_graph['steps'][0]['key'])->toBe('s1')
        ->and($session->draft_lock_version)->toBe(1)
        ->and($session->draftVersions()->count())->toBe(1);
});

it('rejects a duplicate step key', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('s1', 'delay');

    expect(fn () => $session->addStep('s1', 'delay'))->toThrow(InvalidArgumentException::class);
});

it('updates a step by merging config', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('s1', 'delay', ['seconds' => 5]);

    $session->updateStep('s1', ['seconds' => 10, 'label' => 'wait']);

    $step = collect($session->fresh()->draft_graph['steps'])->firstWhere('key', 's1');
    expect($step['config'])->toBe(['seconds' => 10, 'label' => 'wait']);
});

it('removes a step and any edges touching it', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('s1', 'delay');
    $session->addStep('s2', 'delay');
    $session->connect('s1', 's2');

    $session->removeStep('s1');

    $session->refresh();
    expect($session->draft_graph['steps'])->toHaveCount(1)
        ->and($session->draft_graph['edges'])->toHaveCount(0);
});

it('rejects connecting steps that do not exist yet', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('s1', 'delay');

    expect(fn () => $session->connect('s1', 'missing'))->toThrow(InvalidArgumentException::class);
});

it('drives the same mutations through the agent tools', function () {
    $session = WorkflowBuilderSession::factory()->create();

    (new AddNodeTool($session))->handle(new ToolRequest(['key' => 's1', 'type' => 'delay', 'config_json' => '{"seconds":5}']));
    (new AddNodeTool($session))->handle(new ToolRequest(['key' => 's2', 'type' => 'delay']));
    (new ConnectNodesTool($session))->handle(new ToolRequest(['from' => 's1', 'to' => 's2']));

    $session->refresh();
    expect($session->draft_graph['steps'])->toHaveCount(2)
        ->and($session->draft_graph['edges'])->toHaveCount(1);

    (new RemoveNodeTool($session))->handle(new ToolRequest(['key' => 's2']));

    $session->refresh();
    expect($session->draft_graph['steps'])->toHaveCount(1)
        ->and($session->draft_graph['edges'])->toHaveCount(0);
});
