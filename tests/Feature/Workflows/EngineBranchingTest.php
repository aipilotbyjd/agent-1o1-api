<?php

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\Workflows\WorkflowRunner;

function branchingWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

it('completes a merge when one incoming branch was skipped by a condition edge', function () {
    [$admin, $workspace] = branchingWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $gate = $workflow->steps()->create([
        'key' => 'gate',
        'type' => 'condition',
        'config' => ['field' => '{{ input.mode }}', 'operator' => 'equals', 'value' => 'a'],
    ]);
    $branchA = $workflow->steps()->create(['key' => 'branch_a', 'type' => 'transform', 'config' => ['mapping' => ['a' => '1']]]);
    $branchB = $workflow->steps()->create(['key' => 'branch_b', 'type' => 'transform', 'config' => ['mapping' => ['b' => '2']]]);
    $merge = $workflow->steps()->create(['key' => 'merge', 'type' => 'merge']);

    $workflow->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $branchA->id, 'condition' => 'true']);
    $workflow->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $branchB->id, 'condition' => 'false']);
    $workflow->edges()->create(['from_step_id' => $branchA->id, 'to_step_id' => $merge->id]);
    $workflow->edges()->create(['from_step_id' => $branchB->id, 'to_step_id' => $merge->id]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['mode' => 'a']],
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    $mergeStep = $run->steps()->where('key', 'merge')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($mergeStep)->not->toBeNull()
        ->and($mergeStep->status)->toBe(RunStepStatus::Completed)
        ->and($mergeStep->output['branches'])->toBe(['branch_a' => ['a' => '1'], 'branch_b' => null]);
});

it('marks steps on a branch the condition excluded as skipped', function () {
    [$admin, $workspace] = branchingWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $gate = $workflow->steps()->create([
        'key' => 'gate',
        'type' => 'condition',
        'config' => ['field' => '{{ input.mode }}', 'operator' => 'equals', 'value' => 'a'],
    ]);
    $taken = $workflow->steps()->create(['key' => 'taken', 'type' => 'transform', 'config' => ['mapping' => ['x' => '1']]]);
    $skipped = $workflow->steps()->create(['key' => 'skipped', 'type' => 'transform', 'config' => ['mapping' => ['y' => '2']]]);
    $after = $workflow->steps()->create(['key' => 'after_skipped', 'type' => 'transform', 'config' => ['mapping' => ['z' => '3']]]);

    $workflow->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $taken->id, 'condition' => 'true']);
    $workflow->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $skipped->id, 'condition' => 'false']);
    $workflow->edges()->create(['from_step_id' => $skipped->id, 'to_step_id' => $after->id]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['mode' => 'a']],
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->where('key', 'skipped')->first()->status)->toBe(RunStepStatus::Skipped)
        ->and($run->steps()->where('key', 'after_skipped')->first()->status)->toBe(RunStepStatus::Skipped);
});

it('does not complete a run while another step is still in flight', function () {
    [$admin, $workspace] = branchingWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $a = $workflow->steps()->create(['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['a' => '1']]]);
    $workflow->publishVersion();

    $run = Run::create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'workflow_version_id' => $workflow->current_version_id,
        'trigger_type' => 'manual',
        'input' => [],
    ]);
    $run->markRunning();

    // A sibling step that is still pending — the run must not be finished out from under it.
    $run->steps()->create(['key' => 'sibling', 'type' => 'transform', 'input' => []]);

    app(WorkflowRunner::class)->executeStep($run, 'a');

    expect($run->fresh()->status)->toBe(RunStatus::Running);
});

it('routes a failed step down an error edge instead of failing the run', function () {
    [$admin, $workspace] = branchingWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $boom = $workflow->steps()->create([
        'key' => 'boom',
        'type' => 'agent',
        'config' => ['agent_id' => 999999],
    ]);
    $recover = $workflow->steps()->create([
        'key' => 'recover',
        'type' => 'transform',
        'config' => ['mapping' => ['recovered' => 'yes']],
    ]);

    $workflow->edges()->create(['from_step_id' => $boom->id, 'to_step_id' => $recover->id, 'condition' => 'error']);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->where('key', 'boom')->first()->status)->toBe(RunStepStatus::Failed)
        ->and($run->steps()->where('key', 'recover')->first()->output)->toBe(['recovered' => 'yes']);
});
