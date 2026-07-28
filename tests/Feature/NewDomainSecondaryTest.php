<?php

use App\Models\Agents\AgentSkill;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowShare;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function secondaryWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('forbids a regular member from creating a git sync config', function () {
    [$user, $workspace] = secondaryWorkspace('member');

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/git-sync-configs", [
            'repository' => 'acme/workflows',
        ])->assertForbidden();
});

it('redacts the access token and webhook secret in git sync config responses', function () {
    [$user, $workspace] = secondaryWorkspace();

    $response = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/git-sync-configs", [
            'repository' => 'acme/workflows',
            'access_token' => 'ghp_supersecret',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.has_access_token', true)
        ->assertJsonMissingPath('data.access_token');

    $raw = DB::table('git_sync_configs')->first();
    expect($raw->access_token)->not->toBe('ghp_supersecret');
});

it('reports an expired workflow share as expired', function () {
    $share = WorkflowShare::factory()->create(['expires_at' => now()->subDay()]);

    expect($share->isExpired())->toBeTrue();

    $response = $this->getJson("/api/v1/shared-workflows/{$share->token}");
    $response->assertStatus(410);
});

it('soft deletes an agent skill and excludes it from the default listing', function () {
    [$user, $workspace] = secondaryWorkspace();
    $skill = AgentSkill::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/agent-skills/{$skill->id}")
        ->assertOk();

    expect(AgentSkill::find($skill->id))->toBeNull()
        ->and(AgentSkill::withTrashed()->find($skill->id))->not->toBeNull();

    $this->withToken(authHeader($user))
        ->getJson("/api/v1/workspaces/{$workspace->id}/agent-skills")
        ->assertOk()->assertJsonCount(0, 'data');
});

it('forbids a non-member from viewing workflow contract snapshots', function () {
    [, $workspace] = secondaryWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $outsider = User::factory()->create();

    $this->withToken(authHeader($outsider))
        ->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots")
        ->assertForbidden();
});

it('rejects a pending workflow approval and leaves the workflow unpublished', function () {
    [$user, $workspace] = secondaryWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/approvals", []);
    $approvalId = $response->json('data.id');

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/approvals/{$approvalId}/reject")
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($workflow->fresh()->current_version_id)->toBeNull();
});
