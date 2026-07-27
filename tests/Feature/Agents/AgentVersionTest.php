<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function agentVersionSetup(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

it('creates version 1 when an agent is created via the api', function () {
    [$admin, $workspace] = agentVersionSetup();

    $response = $this->withToken(authHeader($admin))->postJson("/api/v1/workspaces/{$workspace->id}/agents", [
        'name' => 'Support Bot',
        'instructions' => 'Be helpful.',
    ]);

    $response->assertCreated();
    $agent = Agent::find($response->json('data.id'));
    expect($agent->versions()->count())->toBe(1)
        ->and($agent->versions()->first()->snapshot['instructions'])->toBe('Be helpful.');
});

it('snapshots a new version on behavioral change but not on rename', function () {
    [$admin, $workspace] = agentVersionSetup();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $agent->snapshotVersion();

    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}",
        ['name' => 'Renamed Bot'],
    )->assertOk();
    expect($agent->versions()->count())->toBe(1);

    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}",
        ['instructions' => 'New behavior.'],
    )->assertOk();
    expect($agent->versions()->count())->toBe(2)
        ->and($agent->versions()->orderByDesc('version')->first()->snapshot['instructions'])->toBe('New behavior.');
});

it('snapshots a new version when tools change', function () {
    [$admin, $workspace] = agentVersionSetup();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $agent->snapshotVersion();
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tools",
        ['tool_ids' => [$tool->id]],
    )->assertOk();

    expect($agent->versions()->count())->toBe(2)
        ->and($agent->versions()->orderByDesc('version')->first()->snapshot['tool_ids'])->toBe([$tool->id]);

    // Syncing the same set again is a no-op.
    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tools",
        ['tool_ids' => [$tool->id]],
    )->assertOk();
    expect($agent->versions()->count())->toBe(2);
});

it('stamps the agent version on chat runs', function () {
    WorkspaceAgent::fake(['Hi!']);
    [$admin, $workspace] = agentVersionSetup();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $agent->snapshotVersion();
    $agent->update(['instructions' => 'v2 behavior']);
    $agent->snapshotVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/chat",
        ['message' => 'hello'],
    )->assertOk();

    expect(Run::query()->latest('id')->first()->agent_version)->toBe(2);
});

it('restores an old version as a new version', function () {
    [$admin, $workspace] = agentVersionSetup();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id, 'instructions' => 'Original.']);
    $agent->snapshotVersion();
    $agent->update(['instructions' => 'Changed.']);
    $agent->snapshotVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions/1/restore",
    )->assertOk();

    $agent->refresh();
    expect($agent->instructions)->toBe('Original.')
        ->and($agent->versions()->count())->toBe(3)
        ->and($agent->versions()->max('version'))->toBe(3);
});

it('lists versions newest first and forbids restore for members', function () {
    [$admin, $workspace] = agentVersionSetup();
    $member = User::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $agent->snapshotVersion();
    $agent->update(['instructions' => 'v2']);
    $agent->snapshotVersion();

    $this->withToken(authHeader($admin))->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions",
    )->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.version', 2);

    $this->app['auth']->forgetGuards();

    $this->withToken(authHeader($member))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/versions/1/restore",
    )->assertForbidden();
});
