<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

it('upserts a notification preference', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->putJson("/api/v1/workspaces/{$workspace->id}/notification-preferences", [
        'event_key' => 'workspace.member_joined',
        'in_app' => false,
        'email' => true,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('notification_preferences', [
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'event_key' => 'workspace.member_joined',
        'in_app' => false,
        'email' => true,
    ]);
});

it('updates an existing preference instead of duplicating it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $this->withToken(authHeader($owner))->putJson("/api/v1/workspaces/{$workspace->id}/notification-preferences", [
        'event_key' => 'workspace.member_joined',
        'in_app' => true,
    ]);

    $this->withToken(authHeader($owner))->putJson("/api/v1/workspaces/{$workspace->id}/notification-preferences", [
        'event_key' => 'workspace.member_joined',
        'in_app' => false,
    ]);

    $this->assertDatabaseCount('notification_preferences', 1);
    $this->assertDatabaseHas('notification_preferences', ['event_key' => 'workspace.member_joined', 'in_app' => false]);
});

it('lists the authenticated user\'s preferences for a workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $this->withToken(authHeader($owner))->putJson("/api/v1/workspaces/{$workspace->id}/notification-preferences", [
        'event_key' => 'workspace.member_joined',
    ]);

    $response = $this->withToken(authHeader($owner))->getJson("/api/v1/workspaces/{$workspace->id}/notification-preferences");

    $response->assertOk()->assertJsonCount(1, 'data');
});
