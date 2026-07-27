<?php

use App\Enums\RunStatus;
use App\Enums\RunStepStatus;
use App\Models\Run;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

function engineWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

it('waits for every parallel branch before running a merge step', function () {
    [$admin, $workspace] = engineWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $start = $workflow->steps()->create(['key' => 'start', 'type' => 'transform', 'config' => ['mapping' => ['ok' => 'yes']]]);
    $branchA = $workflow->steps()->create(['key' => 'branch_a', 'type' => 'transform', 'config' => ['mapping' => ['a' => '1']]]);
    $branchB = $workflow->steps()->create(['key' => 'branch_b', 'type' => 'transform', 'config' => ['mapping' => ['b' => '2']]]);
    $merge = $workflow->steps()->create(['key' => 'merge', 'type' => 'merge']);

    $workflow->edges()->create(['from_step_id' => $start->id, 'to_step_id' => $branchA->id]);
    $workflow->edges()->create(['from_step_id' => $start->id, 'to_step_id' => $branchB->id]);
    $workflow->edges()->create(['from_step_id' => $branchA->id, 'to_step_id' => $merge->id]);
    $workflow->edges()->create(['from_step_id' => $branchB->id, 'to_step_id' => $merge->id]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->where('key', 'merge')->count())->toBe(1);

    $mergeStep = $run->steps()->where('key', 'merge')->first();
    expect($mergeStep->status)->toBe(RunStepStatus::Completed)
        ->and($mergeStep->output['branches'])->toBe(['branch_a' => ['a' => '1'], 'branch_b' => ['b' => '2']]);
});

it('retries a failing step and fails the run once attempts are exhausted', function () {
    [$admin, $workspace] = engineWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $workflow->steps()->create([
        'key' => 'missing_agent',
        'type' => 'agent',
        'config' => ['agent_id' => 999999, 'max_attempts' => 3, 'retry_delay_seconds' => 0],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->where('key', 'missing_agent')->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($step->status)->toBe(RunStepStatus::Failed)
        ->and($step->attempt)->toBe(3);
});

it('waits for a sub-workflow child run to finish before completing the parent step', function () {
    [$admin, $workspace] = engineWorkspace();

    $child = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $childStep = $child->steps()->create(['key' => 'child_step', 'type' => 'transform', 'config' => ['mapping' => ['done' => 'yes']]]);
    $child->publishVersion();

    $parent = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $parent->steps()->create([
        'key' => 'run_child',
        'type' => 'sub_workflow',
        'config' => ['workflow_id' => $child->id],
    ]);
    $parent->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$parent->id}/trigger",
    )->assertCreated();

    $parentRun = Run::query()->where('runnable_id', $parent->id)->latest('id')->first();
    $childRun = Run::query()->where('runnable_id', $child->id)->latest('id')->first();

    expect($childRun->parent_run_id)->toBe($parentRun->id)
        ->and($childRun->status)->toBe(RunStatus::Completed)
        ->and($parentRun->status)->toBe(RunStatus::Completed);

    $step = $parentRun->steps()->where('key', 'run_child')->first();
    expect($step->status)->toBe(RunStepStatus::Completed)
        ->and($step->output['run_id'])->toBe($childRun->id)
        ->and($step->output['output'])->toBe(['done' => 'yes']);
});

it('maps a template over every item with a loop step', function () {
    [$admin, $workspace] = engineWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $workflow->steps()->create([
        'key' => 'each_item',
        'type' => 'loop',
        'config' => ['items' => 'input.items', 'mapping' => ['label' => '{{ item.name }}']],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => [['name' => 'a'], ['name' => 'b']]]],
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->where('key', 'each_item')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->output['count'])->toBe(2)
        ->and($step->output['results'])->toBe([['label' => 'a'], ['label' => 'b']]);
});

it('resolves workspace variables inside step templates', function () {
    [$admin, $workspace] = engineWorkspace();
    Variable::factory()->create(['workspace_id' => $workspace->id, 'key' => 'greeting', 'value' => 'hello']);
    Variable::factory()->secret()->create(['workspace_id' => $workspace->id, 'key' => 'api_token', 'value' => 'super-secret']);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'greet',
        'type' => 'transform',
        'config' => ['mapping' => ['message' => '{{ variables.greeting }}', 'token' => '{{ variables.api_token }}']],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->where('key', 'greet')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->output['message'])->toBe('hello')
        ->and($step->output['token'])->toBe('super-secret');
});
