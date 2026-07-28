<?php

use App\Models\Agents\Agent;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Http;

function toolWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('lists tools for a workspace member', function () {
    [$user, $workspace] = toolWorkspace('member');
    Tool::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    Tool::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/tools");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('allows an admin to create an http tool', function () {
    [$user, $workspace] = toolWorkspace();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/tools", [
        'name' => 'Order Lookup',
        'description' => 'Look up an order by its number.',
        'type' => 'http',
        'config' => [
            'url' => 'https://api.example.com/orders',
            'method' => 'GET',
            'parameters' => [
                ['name' => 'order_number', 'type' => 'string', 'description' => 'The order number', 'required' => true],
            ],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.slug', 'order_lookup');
});

it('forbids a regular member from creating a tool', function () {
    [$user, $workspace] = toolWorkspace('member');

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/tools", [
        'name' => 'Order Lookup',
        'description' => 'Look up orders.',
        'type' => 'http',
        'config' => ['url' => 'https://api.example.com/orders'],
    ]);

    $response->assertForbidden();
});

it('rejects an http tool without a url', function () {
    [$user, $workspace] = toolWorkspace();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/tools", [
        'name' => 'Broken',
        'description' => 'No url.',
        'type' => 'http',
        'config' => ['method' => 'GET'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('config.url');
});

it('executes a tool via the test endpoint', function () {
    Http::fake(['api.example.com/*' => Http::response(['status' => 'shipped'], 200)]);
    [$user, $workspace] = toolWorkspace();
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/tools/{$tool->id}/test",
        ['arguments' => ['query' => 'ORD-1']],
    );

    $response->assertOk();
    expect($response->json('data.result'))->toContain('shipped');
});

it('syncs tools onto an agent within the same workspace only', function () {
    [$user, $workspace] = toolWorkspace();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id]);
    $foreignTool = Tool::factory()->create();

    $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tools",
        ['tool_ids' => [$tool->id]],
    )->assertOk()->assertJsonCount(1, 'data');

    $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/tools",
        ['tool_ids' => [$foreignTool->id]],
    )->assertUnprocessable();
});

it('soft deletes a tool', function () {
    [$user, $workspace] = toolWorkspace();
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->deleteJson("/api/v1/workspaces/{$workspace->id}/tools/{$tool->id}")
        ->assertOk();

    $this->assertSoftDeleted('tools', ['id' => $tool->id]);
});
