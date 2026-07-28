<?php

use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lists workspaces the authenticated user belongs to', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    Workspace::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/workspaces');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('creates a workspace and makes the creator the owner', function () {
    $user = User::factory()->create();

    $response = $this->withToken(authHeader($user))->postJson('/api/v1/workspaces', ['name' => 'Acme Inc']);

    $response->assertCreated()->assertJsonPath('data.name', 'Acme Inc');
    $this->assertDatabaseHas('workspaces', ['name' => 'Acme Inc', 'owner_id' => $user->id]);
    $this->assertDatabaseHas('workspace_members', [
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

it('shows a workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}");

    $response->assertOk()->assertJsonPath('data.id', $workspace->id);
});

it('forbids a non-member from viewing a workspace', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($outsider))->getJson("/api/v1/workspaces/{$workspace->id}");

    $response->assertForbidden();
});

it('allows an admin to update the workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    $response = $this->withToken(authHeader($admin))->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed']);

    $response->assertOk()->assertJsonPath('data.name', 'Renamed');
});

it('forbids a regular member from updating the workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $response = $this->withToken(authHeader($member))->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed']);

    $response->assertForbidden();
});

it('forbids an admin from deleting the workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    $response = $this->withToken(authHeader($admin))->deleteJson("/api/v1/workspaces/{$workspace->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
});

it('allows the owner to delete the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}");

    $response->assertOk();
    $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
});

it('cascades soft deletion to members and invitations', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $ownerMember = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $invitation = WorkspaceInvitation::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertOk();

    $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    $this->assertSoftDeleted('workspace_members', ['id' => $ownerMember->id]);
    $this->assertSoftDeleted('workspace_invitations', ['id' => $invitation->id]);
});

it('excludes soft-deleted workspaces from listing and show', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $workspace->delete();

    $this->withToken(authHeader($owner))->getJson('/api/v1/workspaces')->assertOk()->assertJsonCount(0, 'data');
    $this->withToken(authHeader($owner))->getJson("/api/v1/workspaces/{$workspace->id}")->assertNotFound();
});

it('uploads and replaces the workspace avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $response = $this->withToken(authHeader($user))->post("/api/v1/workspaces/{$workspace->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertOk();
    $path = $workspace->fresh()->avatar;
    Storage::disk('public')->assertExists($path);
});
