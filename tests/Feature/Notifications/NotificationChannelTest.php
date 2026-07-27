<?php

use App\Models\Notifications\NotificationChannel;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Http;

it('allows an admin to create a notification channel', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->postJson("/api/v1/workspaces/{$workspace->id}/notification-channels", [
        'type' => 'discord',
        'name' => 'Team Discord',
        'config' => ['url' => 'https://discord.com/api/webhooks/xyz'],
    ]);

    $response->assertCreated()->assertJsonPath('data.type', 'discord');
    $this->assertDatabaseHas('notification_channels', ['workspace_id' => $workspace->id, 'type' => 'discord']);
});

it('forbids a regular member from creating a notification channel', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $response = $this->withToken(authHeader($member))->postJson("/api/v1/workspaces/{$workspace->id}/notification-channels", [
        'type' => 'discord',
        'name' => 'Team Discord',
        'config' => ['url' => 'https://discord.com/api/webhooks/xyz'],
    ]);

    $response->assertForbidden();
});

it('sends a test notification through the channel', function () {
    Http::fake(['discord.com/*' => Http::response('', 200)]);

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'type' => 'discord',
        'config' => ['url' => 'https://discord.com/api/webhooks/xyz'],
    ]);

    $response = $this->withToken(authHeader($owner))->postJson("/api/v1/workspaces/{$workspace->id}/notification-channels/{$channel->id}/test");

    $response->assertOk();
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/webhooks/xyz' && $request['content'] !== null);
});

it('deletes a notification channel', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::factory()->owner()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $channel = NotificationChannel::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

    $response = $this->withToken(authHeader($owner))->deleteJson("/api/v1/workspaces/{$workspace->id}/notification-channels/{$channel->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
});
