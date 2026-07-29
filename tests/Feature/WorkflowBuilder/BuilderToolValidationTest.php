<?php

use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\DryRunWorkflowTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeSchemaTool;
use App\Ai\Tools\WorkflowBuilder\ListAvailableNodesTool;
use App\Ai\Tools\WorkflowBuilder\ValidateWorkflowTool;
use App\Models\Workflows\WorkflowBuilderSession;
use Laravel\Ai\Tools\Request as ToolRequest;

it('rejects a step whose config is missing a required field', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = (string) (new AddNodeTool($session))->handle(new ToolRequest([
        'key' => 'ask',
        'type' => 'agent',
        'config_json' => '{}',
    ]));

    expect($result)->toContain('missing required config field [agent_id]')
        ->and($session->fresh()->draft_graph['steps'] ?? [])->toBeEmpty();
});

it('rejects a step whose config field is the wrong type', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = (string) (new AddNodeTool($session))->handle(new ToolRequest([
        'key' => 'wait',
        'type' => 'delay',
        'config_json' => '{"seconds": {"nested": true}}',
    ]));

    expect($result)->toContain('must be of type integer');
});

it('accepts a step whose config satisfies the schema', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = (string) (new AddNodeTool($session))->handle(new ToolRequest([
        'key' => 'wait',
        'type' => 'delay',
        'config_json' => '{"seconds": 30}',
    ]));

    expect($result)->toContain('Added step [wait]')
        ->and($session->fresh()->draft_graph['steps'])->toHaveCount(1);
});

it('rejects an unknown step type with a usable hint', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = (string) (new AddNodeTool($session))->handle(new ToolRequest([
        'key' => 'x',
        'type' => 'teleport',
        'config_json' => '{}',
    ]));

    expect($result)->toContain('list_available_nodes');
});

it('inspects a node schema by node type rather than step type', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = json_decode((string) (new InspectNodeSchemaTool($session))->handle(
        new ToolRequest(['node_type' => 'slack.post_message']),
    ), true);

    expect($result['type'])->toBe('slack.post_message')
        ->and($result['step_type'])->toBe('tool')
        ->and($result['credential_type'])->toBe('bearer_token')
        ->and($result['config_schema']['required'])->toContain('channel');
});

it('tells the agent how to recover from an unknown node type', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $result = (string) (new InspectNodeSchemaTool($session))->handle(
        new ToolRequest(['node_type' => 'nope.nope']),
    );

    expect($result)->toContain('list_available_nodes');
});

it('lists every registered node with the fields needed to use it', function () {
    $session = WorkflowBuilderSession::factory()->create();

    $nodes = json_decode((string) (new ListAvailableNodesTool($session))->handle(new ToolRequest([])), true);

    expect($nodes)->not->toBeEmpty();

    $slack = collect($nodes)->firstWhere('node_type', 'slack.post_message');

    expect($slack['step_type'])->toBe('tool')
        ->and($slack['requires_credential'])->toBe('bearer_token')
        ->and(collect($nodes)->pluck('node_type'))->toContain('core.condition', 'http.request');
});

it('reports a valid draft as publishable', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('a', 'delay', ['seconds' => 1]);
    $session->addStep('b', 'delay', ['seconds' => 2]);
    $session->connect('a', 'b');

    expect((string) (new ValidateWorkflowTool($session->fresh()))->handle(new ToolRequest([])))
        ->toContain('valid');
});

it('reports the specific problems with an invalid draft', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('a', 'delay', ['seconds' => 1]);
    $session->addStep('b', 'delay', ['seconds' => 2]);
    $session->connect('a', 'b');
    $session->connect('b', 'a');

    $result = json_decode((string) (new ValidateWorkflowTool($session->fresh()))->handle(new ToolRequest([])), true);

    expect($result['valid'])->toBeFalse()
        ->and(implode(' ', $result['issues']))->toContain('cycle');
});

it('dry runs a draft and reports the execution order', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('fetch', 'tool', ['node' => 'http.request', 'url' => 'https://api.example.com/x']);
    $session->addStep('use', 'transform', ['mapping' => ['status' => '{{ steps.fetch.status }}']]);
    $session->connect('fetch', 'use');

    $result = json_decode((string) (new DryRunWorkflowTool($session->fresh()))->handle(new ToolRequest([])), true);

    expect($result['ok'])->toBeTrue()
        ->and(collect($result['steps'])->pluck('key')->all())->toBe(['fetch', 'use'])
        ->and($result['steps'][0]['node'])->toBe('http.request');
});

it('warns when a template points at data nothing provides', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('use', 'transform', ['mapping' => ['x' => '{{ steps.nonexistent.value }}']]);

    $result = json_decode((string) (new DryRunWorkflowTool($session->fresh()))->handle(new ToolRequest([])), true);

    expect($result['ok'])->toBeFalse()
        ->and(implode(' ', $result['warnings']))->toContain('steps.nonexistent.value');
});

it('warns when a step reads from a step that runs after it', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('first', 'transform', ['mapping' => ['x' => '{{ steps.second.text }}']]);
    $session->addStep('second', 'transform', ['mapping' => ['text' => 'hi']]);
    $session->connect('first', 'second');

    $result = json_decode((string) (new DryRunWorkflowTool($session->fresh()))->handle(new ToolRequest([])), true);

    expect($result['ok'])->toBeFalse()
        ->and(implode(' ', $result['warnings']))->toContain('steps.second.text');
});

it('accepts sample input when dry running', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('greet', 'transform', ['mapping' => ['hello' => '{{ input.name }}']]);

    $result = json_decode((string) (new DryRunWorkflowTool($session->fresh()))->handle(
        new ToolRequest(['sample_input_json' => '{"name":"Ada"}']),
    ), true);

    expect($result['ok'])->toBeTrue()
        ->and($result['steps'][0]['resolved_config']['mapping']['hello'])->toBe('Ada');
});

it('refuses to dry run an invalid graph and reports the issues instead', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $session->addStep('a', 'delay', ['seconds' => 1]);
    $session->addStep('b', 'delay', ['seconds' => 2]);
    $session->connect('a', 'b');
    $session->connect('b', 'a');

    $result = json_decode((string) (new DryRunWorkflowTool($session->fresh()))->handle(new ToolRequest([])), true);

    expect($result['ok'])->toBeFalse()
        ->and($result['steps'])->toBeEmpty()
        ->and(implode(' ', $result['issues']))->toContain('cycle');
});
