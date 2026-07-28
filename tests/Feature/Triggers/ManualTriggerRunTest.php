<?php

use App\Enums\Runs\RunStatus;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerType;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function manualRunSetup(string $role = 'member'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return [$user, $workspace, $workflow];
}

it('starts a run when a member fires a manual trigger', function () {
    [$user, $workspace, $workflow] = manualRunSetup('member');
    $type = TriggerType::firstOrCreate(['key' => 'manual.run'], ['category' => 'manual', 'name' => 'Manual Run', 'mechanism' => 'manual']);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        'type' => 'manual',
        'token' => null,
        'trigger_type_id' => $type->id,
    ]);

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}/run",
    );

    $response->assertStatus(202);

    $run = Run::find($response->json('data.run_id'));
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->trigger_type)->toBe('manual');
});

it('returns 409 when the target already has a run in flight', function () {
    [$user, $workspace, $workflow] = manualRunSetup('member');
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    Run::factory()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'status' => RunStatus::Running,
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}/run",
    )->assertStatus(409);
});

it('forbids an outsider from running a trigger', function () {
    [, $workspace, $workflow] = manualRunSetup('member');
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    $outsider = User::factory()->create();

    $this->withToken(authHeader($outsider))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}/run",
    )->assertForbidden();
});
