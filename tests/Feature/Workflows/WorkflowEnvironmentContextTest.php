<?php

use App\Models\Run;
use App\Models\Variable;
use App\Models\Workflow;
use App\Models\WorkspaceEnvironment;
use App\Services\Workflows\WorkflowRunner;

it('lets a run environment override matching workspace variables', function () {
    $workflow = Workflow::factory()->create();
    $workflow->replaceGraph(
        [['key' => 's1', 'type' => 'transform', 'config' => ['mapping' => ['url' => '{{ variables.api_url }}']], 'position' => null]],
        [],
    );
    $workflow->publishVersion();

    Variable::factory()->create(['workspace_id' => $workflow->workspace_id, 'key' => 'api_url', 'value' => 'https://prod.example.com']);
    $environment = WorkspaceEnvironment::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'variables' => ['api_url' => 'https://staging.example.com'],
    ]);

    $run = Run::create([
        'workspace_id' => $workflow->workspace_id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'workflow_version_id' => $workflow->currentVersion->id,
        'environment_id' => $environment->id,
        'trigger_type' => 'manual',
        'input' => [],
    ]);
    $run->markRunning();

    app(WorkflowRunner::class)->executeStep($run, 's1');

    expect($run->steps()->first()->output)->toBe(['url' => 'https://staging.example.com']);
});

it('falls back to workspace variables when the run has no environment', function () {
    $workflow = Workflow::factory()->create();
    $workflow->replaceGraph(
        [['key' => 's1', 'type' => 'transform', 'config' => ['mapping' => ['url' => '{{ variables.api_url }}']], 'position' => null]],
        [],
    );
    $workflow->publishVersion();

    Variable::factory()->create(['workspace_id' => $workflow->workspace_id, 'key' => 'api_url', 'value' => 'https://prod.example.com']);

    $run = Run::create([
        'workspace_id' => $workflow->workspace_id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'workflow_version_id' => $workflow->currentVersion->id,
        'trigger_type' => 'manual',
        'input' => [],
    ]);
    $run->markRunning();

    app(WorkflowRunner::class)->executeStep($run, 's1');

    expect($run->steps()->first()->output)->toBe(['url' => 'https://prod.example.com']);
});
