<?php

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use Illuminate\Support\Facades\Notification;

function approvalWorkflow(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $gate = $workflow->steps()->create([
        'key' => 'gate',
        'type' => 'human_approval',
        'config' => ['message' => 'Approve order {{ input.order }}?'],
    ]);
    $after = $workflow->steps()->create([
        'key' => 'after',
        'type' => 'transform',
        'config' => ['mapping' => ['done' => 'yes']],
    ]);
    $workflow->edges()->create(['from_step_id' => $gate->id, 'to_step_id' => $after->id, 'condition' => null]);
    $workflow->publishVersion();

    return [$admin, $workspace, $workflow];
}

it('pauses the run at an approval step and notifies admins', function () {
    Notification::fake();
    [$admin, $workspace, $workflow] = approvalWorkflow();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['order' => 'ORD-1']],
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::AwaitingApproval)
        ->and($run->steps()->first()->status)->toBe(RunStepStatus::AwaitingApproval);

    Notification::assertSentTo($admin, RunApprovalRequestedNotification::class,
        fn ($notification) => $notification->body === 'Approve order ORD-1?',
    );
});

it('resumes and completes the run when approved', function () {
    Notification::fake();
    [$admin, $workspace, $workflow] = approvalWorkflow();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    );

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->first();

    $response = $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/steps/{$step->id}/approve",
    );

    $response->assertOk();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe(['done' => 'yes'])
        ->and($run->steps()->where('key', 'after')->first()->status)->toBe(RunStepStatus::Completed);
});

it('fails the run when rejected', function () {
    Notification::fake();
    [$admin, $workspace, $workflow] = approvalWorkflow();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    );

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->first();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/steps/{$step->id}/reject",
    )->assertOk();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->steps()->where('key', 'after')->exists())->toBeFalse();
});

it('forbids a regular member from deciding approvals', function () {
    Notification::fake();
    [$admin, $workspace, $workflow] = approvalWorkflow();
    $member = User::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    );

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->first();

    // Passport's guard caches the resolved user within a test; reset before switching tokens.
    $this->app['auth']->forgetGuards();

    $this->withToken(authHeader($member))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/steps/{$step->id}/approve",
    )->assertForbidden();
});

it('rejects deciding a step that is not awaiting approval', function () {
    Notification::fake();
    [$admin, $workspace, $workflow] = approvalWorkflow();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    );

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->first();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/steps/{$step->id}/approve",
    )->assertOk();

    // Second decision on the same step must fail.
    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/steps/{$step->id}/approve",
    )->assertStatus(400);
});
