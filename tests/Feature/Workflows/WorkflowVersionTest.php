<?php

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Notification;

function versionedWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    return [$admin, $workspace, $workflow];
}

function saveSimpleGraph($test, User $admin, Workspace $workspace, Workflow $workflow, string $value): void
{
    $test->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [['key' => 'emit', 'type' => 'transform', 'config' => ['mapping' => ['value' => $value]]]],
            'edges' => [],
        ],
    )->assertOk();
}

it('publishing snapshots the graph and increments versions', function () {
    [$admin, $workspace, $workflow] = versionedWorkspace();

    saveSimpleGraph($this, $admin, $workspace, $workflow, 'one');
    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/publish",
        ['notes' => 'first release'],
    )->assertOk()->assertJsonPath('data.current_version', 1)->assertJsonPath('data.has_unpublished_changes', false);

    saveSimpleGraph($this, $admin, $workspace, $workflow, 'two');
    expect($workflow->fresh()->has_unpublished_changes)->toBeTrue();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/publish",
    )->assertOk()->assertJsonPath('data.current_version', 2);

    $versions = $this->withToken(authHeader($admin))->getJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions",
    );
    $versions->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.notes', 'first release');
});

it('runs execute the published snapshot, not the edited draft', function () {
    [$admin, $workspace, $workflow] = versionedWorkspace();

    saveSimpleGraph($this, $admin, $workspace, $workflow, 'published-value');
    $workflow->fresh()->publishVersion($admin);

    // Edit the draft after publishing — this must NOT affect runs.
    saveSimpleGraph($this, $admin, $workspace, $workflow, 'draft-only-value');

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe(['value' => 'published-value'])
        ->and($run->workflowVersion->version)->toBe(1);
});

it('a paused run resumes on its original version even after republish', function () {
    Notification::fake();
    [$admin, $workspace, $workflow] = versionedWorkspace();

    // v1: approval gate -> emit "old"
    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [
                ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
                ['key' => 'emit', 'type' => 'transform', 'config' => ['mapping' => ['value' => 'old']]],
            ],
            'edges' => [['from' => 'gate', 'to' => 'emit']],
        ],
    )->assertOk();
    $workflow->fresh()->publishVersion($admin);

    // Start a run; it pauses at the gate on v1.
    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();
    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::AwaitingApproval);

    // Republish a changed graph as v2 while the run is paused.
    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [
                ['key' => 'gate', 'type' => 'human_approval', 'config' => []],
                ['key' => 'emit', 'type' => 'transform', 'config' => ['mapping' => ['value' => 'new']]],
            ],
            'edges' => [['from' => 'gate', 'to' => 'emit']],
        ],
    )->assertOk();
    $workflow->fresh()->publishVersion($admin);

    // Approve — the paused run must finish on v1's graph.
    $gateStep = $run->steps()->first();
    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/steps/{$gateStep->id}/approve",
    )->assertOk();

    expect($run->fresh()->output)->toBe(['value' => 'old']);
});

it('restores an old version into the draft', function () {
    [$admin, $workspace, $workflow] = versionedWorkspace();

    saveSimpleGraph($this, $admin, $workspace, $workflow, 'one');
    $workflow->fresh()->publishVersion($admin);
    saveSimpleGraph($this, $admin, $workspace, $workflow, 'two');
    $workflow->fresh()->publishVersion($admin);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/1/restore",
    )->assertOk();

    $step = $workflow->fresh()->steps()->first();
    expect($step->config['mapping']['value'])->toBe('one')
        ->and($workflow->fresh()->has_unpublished_changes)->toBeTrue();
});

it('diffs two versions', function () {
    [$admin, $workspace, $workflow] = versionedWorkspace();

    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [
                ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => '1']]],
                ['key' => 'b', 'type' => 'transform', 'config' => ['mapping' => ['y' => '1']]],
            ],
            'edges' => [['from' => 'a', 'to' => 'b']],
        ],
    );
    $workflow->fresh()->publishVersion($admin);

    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [
                ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'CHANGED']]],
                ['key' => 'c', 'type' => 'transform', 'config' => ['mapping' => ['z' => '1']]],
            ],
            'edges' => [['from' => 'a', 'to' => 'c']],
        ],
    );
    $workflow->fresh()->publishVersion($admin);

    $response = $this->withToken(authHeader($admin))->getJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/versions/1/diff/2",
    );

    $response->assertOk()
        ->assertJsonPath('data.steps.added', ['c'])
        ->assertJsonPath('data.steps.removed', ['b'])
        ->assertJsonPath('data.steps.changed', ['a'])
        ->assertJsonPath('data.edges.added', ['a -> c'])
        ->assertJsonPath('data.edges.removed', ['a -> b']);
});

it('fails a run when the workflow was never published', function () {
    [$admin, $workspace, $workflow] = versionedWorkspace();
    $workflow->update(['status' => 'published']); // status forced without a snapshot

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('no published version');
});
