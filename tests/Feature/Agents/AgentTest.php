<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function agentWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('lists agents for a workspace member', function () {
    [$user, $workspace] = agentWorkspace('member');
    Agent::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    Agent::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/agents");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('allows an admin to create an agent with a unique slug', function () {
    [$user, $workspace] = agentWorkspace();

    $payload = ['name' => 'Support Bot', 'instructions' => 'Help customers politely.'];

    $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/agents", $payload)
        ->assertCreated()->assertJsonPath('data.slug', 'support-bot');

    $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/agents", $payload)
        ->assertCreated()->assertJsonPath('data.slug', 'support-bot-2');
});

it('forbids a regular member from creating an agent', function () {
    [$user, $workspace] = agentWorkspace('member');

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/agents", [
        'name' => 'Support Bot',
        'instructions' => 'Help customers.',
    ]);

    $response->assertForbidden();
});

it('validates the provider', function () {
    [$user, $workspace] = agentWorkspace();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/agents", [
        'name' => 'Support Bot',
        'instructions' => 'Help customers.',
        'provider' => 'not-a-provider',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('provider');
});

it('updates an agent', function () {
    [$user, $workspace] = agentWorkspace();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))->putJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}", [
        'instructions' => 'New instructions.',
        'temperature' => 0.5,
    ]);

    $response->assertOk()->assertJsonPath('data.instructions', 'New instructions.');
});

it('returns 404 for an agent in another workspace', function () {
    [$user, $workspace] = agentWorkspace();
    $foreignAgent = Agent::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$foreignAgent->id}");

    $response->assertNotFound();
});

it('soft deletes an agent', function () {
    [$user, $workspace] = agentWorkspace();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->deleteJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}")
        ->assertOk();

    $this->assertSoftDeleted('agents', ['id' => $agent->id]);
});
