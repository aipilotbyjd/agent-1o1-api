<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowShare;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function cloneTargetWorkspace(string $role = 'member'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('clones a shared workflow into the target workspace when allow_clone is true', function () {
    $sourceWorkspace = Workspace::factory()->create();
    $source = Workflow::factory()->create(['workspace_id' => $sourceWorkspace->id, 'name' => 'Original']);
    $source->replaceGraph([['key' => 's1', 'type' => 'delay', 'config' => ['seconds' => 1], 'position' => null]], []);
    $source->publishVersion();

    $share = WorkflowShare::factory()->create(['workflow_id' => $source->id, 'workspace_id' => $sourceWorkspace->id, 'allow_clone' => true]);

    [$user, $targetWorkspace] = cloneTargetWorkspace();

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$targetWorkspace->id}/shared-workflows/{$share->token}/clone",
    );

    $response->assertCreated()->assertJsonPath('data.name', 'Original (Copy)');

    $clone = Workflow::where('workspace_id', $targetWorkspace->id)->first();
    expect($clone)->not->toBeNull()
        ->and($clone->steps()->count())->toBe(1)
        ->and($share->fresh()->view_count)->toBe(1);
});

it('rejects cloning when allow_clone is false', function () {
    $share = WorkflowShare::factory()->create(['allow_clone' => false]);
    [$user, $targetWorkspace] = cloneTargetWorkspace();

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$targetWorkspace->id}/shared-workflows/{$share->token}/clone",
    )->assertForbidden();
});

it('rejects cloning an expired share', function () {
    $share = WorkflowShare::factory()->create(['allow_clone' => true, 'expires_at' => now()->subDay()]);
    [$user, $targetWorkspace] = cloneTargetWorkspace();

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$targetWorkspace->id}/shared-workflows/{$share->token}/clone",
    )->assertStatus(410);
});

it('forbids a non-member of the target workspace from cloning', function () {
    $share = WorkflowShare::factory()->create(['allow_clone' => true]);
    $targetWorkspace = Workspace::factory()->create();
    $outsider = User::factory()->create();

    $this->withToken(authHeader($outsider))->postJson(
        "/api/v1/workspaces/{$targetWorkspace->id}/shared-workflows/{$share->token}/clone",
    )->assertForbidden();
});
