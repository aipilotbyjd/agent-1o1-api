<?php

use App\Enums\RunStatus;
use App\Enums\RunStepStatus;
use App\Events\RunUpdated;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Event;

function workspaceWithMember(string $role = 'member'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('lists runs for a workspace member', function () {
    [$user, $workspace] = workspaceWithMember();
    Run::factory()->count(3)->create(['workspace_id' => $workspace->id]);
    Run::factory()->create(); // other workspace

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/runs");

    $response->assertOk()->assertJsonCount(3, 'data.data');
});

it('filters runs by status', function () {
    [$user, $workspace] = workspaceWithMember();
    Run::factory()->completed()->count(2)->create(['workspace_id' => $workspace->id]);
    Run::factory()->failed()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/runs?status=failed");

    $response->assertOk()->assertJsonCount(1, 'data.data');
});

it('forbids a non-member from listing runs', function () {
    $outsider = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $response = $this->withToken(authHeader($outsider))->getJson("/api/v1/workspaces/{$workspace->id}/runs");

    $response->assertForbidden();
});

it('shows a run with its steps', function () {
    [$user, $workspace] = workspaceWithMember();
    $run = Run::factory()->completed()->create(['workspace_id' => $workspace->id]);
    RunStep::factory()->completed()->count(2)->create(['run_id' => $run->id]);

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}");

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonCount(2, 'data.steps');
});

it('returns 404 for a run in another workspace', function () {
    [$user, $workspace] = workspaceWithMember();
    $foreignRun = Run::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$foreignRun->id}");

    $response->assertNotFound();
});

it('lets the user who triggered a run cancel it and cancels pending steps', function () {
    Event::fake([RunUpdated::class]);
    [$user, $workspace] = workspaceWithMember();
    $run = Run::factory()->running()->create(['workspace_id' => $workspace->id, 'triggered_by' => $user->id]);
    $step = RunStep::factory()->running()->create(['run_id' => $run->id]);
    $doneStep = RunStep::factory()->completed()->create(['run_id' => $run->id]);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($run->fresh()->status)->toBe(RunStatus::Cancelled)
        ->and($step->fresh()->status)->toBe(RunStepStatus::Cancelled)
        ->and($doneStep->fresh()->status)->toBe(RunStepStatus::Completed);
    Event::assertDispatched(RunUpdated::class);
});

it('lets a workspace admin cancel any run', function () {
    [$admin, $workspace] = workspaceWithMember('admin');
    $run = Run::factory()->running()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($admin))->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel");

    $response->assertOk();
});

it('forbids a regular member from cancelling a run they did not trigger', function () {
    [$member, $workspace] = workspaceWithMember();
    $run = Run::factory()->running()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($member))->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel");

    $response->assertForbidden();
});

it('rejects cancelling a run that already finished', function () {
    [$user, $workspace] = workspaceWithMember();
    $run = Run::factory()->completed()->create(['workspace_id' => $workspace->id, 'triggered_by' => $user->id]);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel");

    $response->assertStatus(400);
});
