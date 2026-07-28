<?php

use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceInvitation;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\Workspace\MemberInvitedNotification;
use App\Notifications\Workspace\MemberJoinedNotification;
use App\Notifications\Workspace\MemberRemovedNotification;
use App\Notifications\Workspace\MemberRoleChangedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('notifies other admins when a member is invited', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    $this->withToken(authHeader($owner))->postJson("/api/v1/workspaces/{$workspace->id}/members/invite", [
        'email' => 'invitee@example.com',
        'role' => 'member',
    ])->assertCreated();

    Notification::assertSentTo($admin, MemberInvitedNotification::class);
    Notification::assertNotSentTo($owner, MemberInvitedNotification::class);
});

it('notifies admins when an invitation is accepted', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'invited_by' => $owner->id,
    ]);

    $signedUrl = URL::temporarySignedRoute('v1.workspaces.invitations.accept', $invitation->expires_at, ['token' => $invitation->token]);

    $this->withToken(authHeader($invitee))->getJson($signedUrl)->assertOk();

    Notification::assertSentTo($owner, MemberJoinedNotification::class);
});

it('notifies the promoted member\'s fellow admins when a role changes', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $member = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($owner))->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
        'role' => 'admin',
    ])->assertOk();

    Notification::assertSentTo($admin, MemberRoleChangedNotification::class);
    Notification::assertNotSentTo($owner, MemberRoleChangedNotification::class);
});

it('notifies other admins when a member is removed', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $member = WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")->assertOk();

    Notification::assertSentTo($admin, MemberRemovedNotification::class);
    Notification::assertNotSentTo($owner, MemberRemovedNotification::class);
});
