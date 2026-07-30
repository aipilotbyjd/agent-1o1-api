<?php

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

/**
 * @return array{0: User, 1: Workspace, 2: Workflow}
 */
function waitWorkflow(array $waitConfig = []): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $wait = $workflow->steps()->create(['key' => 'hold', 'type' => 'wait', 'config' => $waitConfig]);
    $after = $workflow->steps()->create([
        'key' => 'after',
        'type' => 'transform',
        'config' => ['mapping' => ['resumed' => '{{ steps.hold.data.ticket }}']],
    ]);
    $workflow->edges()->create(['from_step_id' => $wait->id, 'to_step_id' => $after->id]);
    $workflow->publishVersion();

    return [$admin, $workspace, $workflow];
}

function triggerWaitRun(User $admin, Workspace $workspace, Workflow $workflow): Run
{
    test()->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    return Run::query()->latest('id')->first();
}

it('parks the run on a wait step instead of completing it', function () {
    [$admin, $workspace, $workflow] = waitWorkflow();

    $run = triggerWaitRun($admin, $workspace, $workflow);
    $step = $run->steps()->where('key', 'hold')->first();

    expect($run->status)->toBe(RunStatus::AwaitingCallback)
        ->and($step->status)->toBe(RunStepStatus::AwaitingCallback)
        ->and($step->callback_token)->not->toBeNull()
        ->and($step->callback_expires_at)->not->toBeNull()
        ->and($run->steps()->where('key', 'after')->exists())->toBeFalse();
});

it('resumes the run and advances the graph when the callback is hit', function () {
    [$admin, $workspace, $workflow] = waitWorkflow();

    $run = triggerWaitRun($admin, $workspace, $workflow);
    $token = $run->steps()->where('key', 'hold')->first()->callback_token;

    $this->postJson("/api/v1/run-callbacks/{$token}", ['ticket' => 'T-42'])->assertOk();

    $run->refresh();
    $after = $run->steps()->where('key', 'after')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->where('key', 'hold')->first()->output)
        ->toBe(['data' => ['ticket' => 'T-42'], 'timed_out' => false])
        ->and($after->status)->toBe(RunStepStatus::Completed)
        ->and($after->output)->toBe(['resumed' => 'T-42']);
});

it('rejects a replayed callback once the step has resumed', function () {
    [$admin, $workspace, $workflow] = waitWorkflow();

    $run = triggerWaitRun($admin, $workspace, $workflow);
    $token = $run->steps()->where('key', 'hold')->first()->callback_token;

    $this->postJson("/api/v1/run-callbacks/{$token}", ['ticket' => 'T-1'])->assertOk();
    $this->postJson("/api/v1/run-callbacks/{$token}", ['ticket' => 'T-2'])->assertNotFound();

    expect($run->fresh()->steps()->where('key', 'after')->count())->toBe(1);
});

it('404s an unknown callback token', function () {
    $this->postJson('/api/v1/run-callbacks/nope')->assertNotFound();
});

it('fails the run when a wait step times out', function () {
    [$admin, $workspace, $workflow] = waitWorkflow(['timeout_minutes' => 5]);

    $run = triggerWaitRun($admin, $workspace, $workflow);

    $this->travel(6)->minutes();
    $this->artisan('workflows:expire-waiting-callbacks')->assertSuccessful();

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->steps()->where('key', 'hold')->first()->status)->toBe(RunStepStatus::Failed)
        ->and($run->steps()->where('key', 'after')->exists())->toBeFalse();
});

it('carries on past a timed out wait step when continue_on_timeout is set', function () {
    [$admin, $workspace, $workflow] = waitWorkflow([
        'timeout_minutes' => 5,
        'continue_on_timeout' => true,
    ]);

    $run = triggerWaitRun($admin, $workspace, $workflow);

    $this->travel(6)->minutes();
    $this->artisan('workflows:expire-waiting-callbacks')->assertSuccessful();

    $run->refresh();
    $hold = $run->steps()->where('key', 'hold')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($hold->status)->toBe(RunStepStatus::Completed)
        ->and($hold->output['timed_out'])->toBeTrue()
        ->and($run->steps()->where('key', 'after')->first()->status)->toBe(RunStepStatus::Completed);
});

it('exposes the callback url on the parked step and hides it afterwards', function () {
    [$admin, $workspace, $workflow] = waitWorkflow();

    $run = triggerWaitRun($admin, $workspace, $workflow);
    $token = $run->steps()->where('key', 'hold')->first()->callback_token;

    $parked = $this->withToken(authHeader($admin))
        ->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}")
        ->assertOk()
        ->json('data.steps.0');

    expect($parked['callback_url'])->toContain("/api/v1/run-callbacks/{$token}");

    $this->postJson("/api/v1/run-callbacks/{$token}", [])->assertOk();

    $resumed = $this->withToken(authHeader($admin))
        ->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}")
        ->assertOk()
        ->json('data.steps.0');

    expect($resumed)->not->toHaveKey('callback_url');
});

it('does not let an expired token resume the run', function () {
    [$admin, $workspace, $workflow] = waitWorkflow(['timeout_minutes' => 5]);

    $run = triggerWaitRun($admin, $workspace, $workflow);
    $token = $run->steps()->where('key', 'hold')->first()->callback_token;

    $this->travel(6)->minutes();

    $this->postJson("/api/v1/run-callbacks/{$token}", ['ticket' => 'T-9'])->assertBadRequest();

    expect($run->fresh()->status)->toBe(RunStatus::AwaitingCallback);
});

it('keeps a cancelled run from leaving a wait step parked', function () {
    [$admin, $workspace, $workflow] = waitWorkflow();

    $run = triggerWaitRun($admin, $workspace, $workflow);

    $this->withToken(authHeader($admin))
        ->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel")
        ->assertOk();

    expect(RunStep::query()->find($run->steps()->where('key', 'hold')->first()->id)->status)
        ->toBe(RunStepStatus::Cancelled);
});
