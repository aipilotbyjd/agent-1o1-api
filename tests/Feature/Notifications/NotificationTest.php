<?php

use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Notifications\Workspace\MemberJoinedNotification;

it('lists the authenticated user\'s notifications', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/notifications');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('filters unread notifications', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));
    $user->notifications()->first()->markAsRead();
    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/notifications?unread=1');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('returns the unread count', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));
    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/notifications/unread-count');

    $response->assertOk()->assertJsonPath('data.unread', 2);
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));
    $notification = $user->notifications()->first();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/notifications/{$notification->id}/read");

    $response->assertOk();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));
    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));

    $response = $this->withToken(authHeader($user))->postJson('/api/v1/notifications/mark-all-read');

    $response->assertOk();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('deletes a notification', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $user->notify(new MemberJoinedNotification($workspace, $member->load('user')));
    $notification = $user->notifications()->first();

    $response = $this->withToken(authHeader($user))->deleteJson("/api/v1/notifications/{$notification->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

it('does not expose another user\'s notification', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $member = WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $owner->notify(new MemberJoinedNotification($workspace, $member->load('user')));
    $notification = $owner->notifications()->first();

    $response = $this->withToken(authHeader($intruder))->deleteJson("/api/v1/notifications/{$notification->id}");

    $response->assertNotFound();
});
