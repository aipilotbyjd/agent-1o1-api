<?php

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('lists pending invitations for owners and admins', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    WorkspaceInvitation::factory()->create(['workspace_id' => $workspace->id, 'email' => 'pending@example.com']);
    WorkspaceInvitation::factory()->accepted()->create(['workspace_id' => $workspace->id]);
    WorkspaceInvitation::factory()->expired()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($owner))->getJson("/api/v1/workspaces/{$workspace->id}/invitations");

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'pending@example.com');
});

it('forbids a regular member from listing invitations', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $response = $this->withToken(authHeader($member))->getJson("/api/v1/workspaces/{$workspace->id}/invitations");

    $response->assertForbidden();
});

it('lists workspace members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    WorkspaceMember::factory()->member()->count(2)->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($owner))->getJson("/api/v1/workspaces/{$workspace->id}/members");

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('allows an admin to invite a member and sends the invitation email', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->postJson("/api/v1/workspaces/{$workspace->id}/members/invite", [
        'email' => 'invitee@example.com',
        'role' => 'member',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('workspace_invitations', ['workspace_id' => $workspace->id, 'email' => 'invitee@example.com']);
    Mail::assertQueued(WorkspaceInvitationMail::class);
});

it('rejects inviting a member with the owner role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->postJson("/api/v1/workspaces/{$workspace->id}/members/invite", [
        'email' => 'invitee@example.com',
        'role' => 'owner',
    ]);

    $response->assertStatus(422);
});

it('forbids a regular member from inviting new members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $response = $this->withToken(authHeader($member))->postJson("/api/v1/workspaces/{$workspace->id}/members/invite", [
        'email' => 'invitee@example.com',
        'role' => 'member',
    ]);

    $response->assertForbidden();
});

it('accepts a valid invitation via the signed link', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'v1.workspaces.invitations.accept',
        $invitation->expires_at,
        ['token' => $invitation->token],
    );

    $response = $this->withToken(authHeader($invitee))->getJson($url);

    $response->assertOk();
    $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $invitee->id]);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('lets a previously removed member rejoin via a new invitation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $rejoiningUser = User::factory()->create(['email' => 'rejoiner@example.com']);
    $oldMembership = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $rejoiningUser->id]);
    $oldMembership->delete();

    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'rejoiner@example.com',
        'role' => 'admin',
    ]);

    $url = URL::temporarySignedRoute(
        'v1.workspaces.invitations.accept',
        $invitation->expires_at,
        ['token' => $invitation->token],
    );

    $response = $this->withToken(authHeader($rejoiningUser))->getJson($url);

    $response->assertOk();
    $this->assertDatabaseHas('workspace_members', [
        'id' => $oldMembership->id,
        'workspace_id' => $workspace->id,
        'user_id' => $rejoiningUser->id,
        'role' => 'admin',
        'deleted_at' => null,
    ]);
});

it('rejects an expired invitation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $invitation = WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'v1.workspaces.invitations.accept',
        now()->addMinutes(5),
        ['token' => $invitation->token],
    );

    $response = $this->withToken(authHeader($invitee))->getJson($url);

    $response->assertStatus(422);
});

it('allows an admin to update a member role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $member = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($owner))->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
        'role' => 'admin',
    ]);

    $response->assertOk()->assertJsonPath('data.role', 'admin');
});

it('prevents changing the owner role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $ownerMember = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$ownerMember->id}", [
        'role' => 'admin',
    ]);

    $response->assertStatus(422);
});

it('allows an admin to remove a member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $member = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}");

    $response->assertOk();
    $this->assertSoftDeleted('workspace_members', ['id' => $member->id]);
});

it('allows a member to leave a workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $memberUser = User::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $memberUser->id]);

    $response = $this->withToken(authHeader($memberUser))->deleteJson("/api/v1/workspaces/{$workspace->id}/members/leave");

    $response->assertOk();
    $this->assertSoftDeleted('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $memberUser->id]);
});

it('prevents the owner from leaving the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}/members/leave");

    $response->assertForbidden();
});

it('reflects a role update immediately, without waiting for the cache to expire', function () {
    $owner = User::factory()->create();
    $memberUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $member = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $memberUser->id]);

    // Prime the cache for the member's current (pre-promotion) role.
    $this->withToken(authHeader($memberUser))->getJson("/api/v1/workspaces/{$workspace->id}")->assertOk();

    // Passport's token guard caches the resolved user for the lifetime of the test's
    // container, so switching actors mid-test requires forgetting the cached guard state.
    Auth::forgetGuards();

    $this->withToken(authHeader($owner))->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
        'role' => 'admin',
    ])->assertOk();

    Auth::forgetGuards();

    // Now-admin should immediately be able to perform an admin-only action, no TTL wait.
    $this->withToken(authHeader($memberUser))
        ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed by new admin'])
        ->assertOk();
});

it('revokes access immediately after a member is removed', function () {
    $owner = User::factory()->create();
    $memberUser = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $member = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $memberUser->id]);

    // Prime the cache for the member.
    $this->withToken(authHeader($memberUser))->getJson("/api/v1/workspaces/{$workspace->id}")->assertOk();

    Auth::forgetGuards();

    $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")->assertOk();

    Auth::forgetGuards();

    $this->withToken(authHeader($memberUser))->getJson("/api/v1/workspaces/{$workspace->id}")->assertForbidden();
});
