<?php

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function loopWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

/**
 * A one-step child workflow that echoes the item it was handed.
 */
function loopBody(Workspace $workspace, array $mapping = ['echo' => '{{ input.item }}']): Workflow
{
    $child = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $child->steps()->create(['key' => 'handle', 'type' => 'transform', 'config' => ['mapping' => $mapping]]);
    $child->publishVersion();

    return $child;
}

function loopParent(Workspace $workspace, array $config): Workflow
{
    $parent = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $parent->steps()->create(['key' => 'each', 'type' => 'loop', 'config' => $config]);
    $parent->publishVersion();

    return $parent;
}

it('keeps map mode working as a single-step template map', function () {
    [$admin, $workspace] = loopWorkspace();
    $workflow = loopParent($workspace, [
        'items' => 'input.items',
        'mapping' => ['label' => '{{ item.name }}'],
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => [['name' => 'a'], ['name' => 'b']]]],
    )->assertCreated();

    $step = Run::query()->latest('id')->first()->steps()->where('key', 'each')->first();

    expect($step->output['count'])->toBe(2)
        ->and($step->output['results'])->toBe([['label' => 'a'], ['label' => 'b']]);
});

it('starts one child run per item in foreach mode', function () {
    [$admin, $workspace] = loopWorkspace();
    $child = loopBody($workspace);
    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => $child->id,
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => ['x', 'y', 'z']]],
    )->assertCreated();

    $run = Run::query()->where('runnable_id', $workflow->id)->latest('id')->first();
    $step = $run->steps()->where('key', 'each')->first();
    $children = Run::query()->where('parent_step_id', $step->id)->orderBy('loop_index')->get();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->status)->toBe(RunStepStatus::Completed)
        ->and($children)->toHaveCount(3)
        ->and($children->pluck('loop_index')->all())->toBe([0, 1, 2])
        ->and($step->output['count'])->toBe(3)
        ->and($step->output['failed'])->toBe(0)
        ->and($children->first()->input)->toBe(['item' => 'x', 'index' => 0]);
});

it('isolates each iteration so one failure does not stop the others', function () {
    [$admin, $workspace] = loopWorkspace();

    // A child that fails only when handed the poisoned item.
    $child = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $gate = $child->steps()->create([
        'key' => 'gate',
        'type' => 'condition',
        'config' => ['field' => '{{ input.item }}', 'operator' => 'equals', 'value' => 'bad'],
    ]);
    $boom = $child->steps()->create(['key' => 'boom', 'type' => 'agent', 'config' => ['agent_id' => 999999]]);
    $ok = $child->steps()->create(['key' => 'ok', 'type' => 'transform', 'config' => ['mapping' => ['fine' => 'yes']]]);
    $child->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $boom->id, 'condition' => 'true']);
    $child->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $ok->id, 'condition' => 'false']);
    $child->publishVersion();

    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => $child->id,
        'on_item_error' => 'collect_errors',
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => ['good', 'bad', 'good']]],
    )->assertCreated();

    $run = Run::query()->where('runnable_id', $workflow->id)->latest('id')->first();
    $step = $run->steps()->where('key', 'each')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->status)->toBe(RunStepStatus::Completed)
        ->and($step->output['count'])->toBe(3)
        ->and($step->output['failed'])->toBe(1)
        ->and($step->output['errors'][0]['index'])->toBe(1);
});

it('fails the run on the first bad item under fail_fast', function () {
    [$admin, $workspace] = loopWorkspace();

    $child = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $child->steps()->create(['key' => 'boom', 'type' => 'agent', 'config' => ['agent_id' => 999999]]);
    $child->publishVersion();

    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => $child->id,
        'on_item_error' => 'fail_fast',
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => ['a', 'b']]],
    )->assertCreated();

    $run = Run::query()->where('runnable_id', $workflow->id)->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->steps()->where('key', 'each')->first()->status)->toBe(RunStepStatus::Failed);
});

it('drops failed iterations from the results under the continue policy', function () {
    [$admin, $workspace] = loopWorkspace();

    $child = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $gate = $child->steps()->create([
        'key' => 'gate',
        'type' => 'condition',
        'config' => ['field' => '{{ input.item }}', 'operator' => 'equals', 'value' => 'bad'],
    ]);
    $boom = $child->steps()->create(['key' => 'boom', 'type' => 'agent', 'config' => ['agent_id' => 999999]]);
    $ok = $child->steps()->create(['key' => 'ok', 'type' => 'transform', 'config' => ['mapping' => ['fine' => 'yes']]]);
    $child->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $boom->id, 'condition' => 'true']);
    $child->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $ok->id, 'condition' => 'false']);
    $child->publishVersion();

    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => $child->id,
        'on_item_error' => 'continue',
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => ['good', 'bad']]],
    )->assertCreated();

    $step = Run::query()->where('runnable_id', $workflow->id)->latest('id')->first()
        ->steps()->where('key', 'each')->first();

    expect($step->output['failed'])->toBe(1)
        ->and($step->output['results'])->toHaveCount(1)
        ->and($step->output['results'][0]['index'])->toBe(0);
});

it('releases queued iterations as earlier ones settle when concurrency is capped', function () {
    [$admin, $workspace] = loopWorkspace();
    $child = loopBody($workspace);
    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => $child->id,
        'max_concurrent' => 1,
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => ['a', 'b', 'c', 'd']]],
    )->assertCreated();

    $run = Run::query()->where('runnable_id', $workflow->id)->latest('id')->first();
    $step = $run->steps()->where('key', 'each')->first();

    // Every item still runs, one at a time rather than all at once.
    expect($run->status)->toBe(RunStatus::Completed)
        ->and(Run::query()->where('parent_step_id', $step->id)->count())->toBe(4)
        ->and($step->output['count'])->toBe(4);
});

it('completes immediately when the item list is empty', function () {
    [$admin, $workspace] = loopWorkspace();
    $child = loopBody($workspace);
    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => $child->id,
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => []]],
    )->assertCreated();

    $run = Run::query()->where('runnable_id', $workflow->id)->latest('id')->first();
    $step = $run->steps()->where('key', 'each')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->output['count'])->toBe(0)
        ->and(Run::query()->where('parent_step_id', $step->id)->count())->toBe(0);
});

it('fails a foreach loop that references a missing workflow', function () {
    [$admin, $workspace] = loopWorkspace();
    $workflow = loopParent($workspace, [
        'mode' => 'foreach',
        'items' => 'input.items',
        'workflow_id' => 999999,
    ]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['items' => ['a']]],
    )->assertCreated();

    expect(Run::query()->where('runnable_id', $workflow->id)->latest('id')->first()->status)
        ->toBe(RunStatus::Failed);
});
