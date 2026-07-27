<?php

use App\Models\Nodes\Node;
use App\Models\Nodes\NodeCategory;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function nodeWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('lists builtin nodes plus this workspace custom nodes only', function () {
    [$user, $workspace] = nodeWorkspace('member');
    Node::factory()->create(['workspace_id' => null]);
    Node::factory()->custom()->create(['workspace_id' => $workspace->id]);
    Node::factory()->custom()->create(); // another workspace's custom node

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/nodes");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('filters nodes by category', function () {
    [$user, $workspace] = nodeWorkspace('member');
    $category = NodeCategory::factory()->create();
    Node::factory()->create(['category_id' => $category->id]);
    Node::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/nodes?category_id={$category->id}");

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('allows an admin to create a custom node', function () {
    [$user, $workspace] = nodeWorkspace();
    $category = NodeCategory::factory()->create();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/nodes", [
        'category_id' => $category->id,
        'step_type' => 'tool',
        'name' => 'My Custom Node',
        'icon' => 'bolt',
        'color' => '#000000',
        'config_schema' => ['type' => 'object', 'properties' => []],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_custom', true)
        ->assertJsonPath('data.workspace_id', $workspace->id);
});

it('forbids a regular member from creating a custom node', function () {
    [$user, $workspace] = nodeWorkspace('member');
    $category = NodeCategory::factory()->create();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/nodes", [
        'category_id' => $category->id,
        'step_type' => 'tool',
        'name' => 'My Custom Node',
        'icon' => 'bolt',
        'color' => '#000000',
        'config_schema' => ['type' => 'object'],
    ]);

    $response->assertForbidden();
});

it('prevents updating a builtin catalog node', function () {
    [$user, $workspace] = nodeWorkspace();
    $node = Node::factory()->create(['workspace_id' => null]);

    $response = $this->withToken(authHeader($user))->putJson("/api/v1/workspaces/{$workspace->id}/nodes/{$node->id}", [
        'name' => 'Renamed',
    ]);

    $response->assertForbidden();
});

it('allows deleting a custom node owned by the workspace', function () {
    [$user, $workspace] = nodeWorkspace();
    $node = Node::factory()->custom()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))->deleteJson("/api/v1/workspaces/{$workspace->id}/nodes/{$node->id}");

    $response->assertOk();
    expect(Node::find($node->id))->toBeNull();
});

it('returns 404 for a custom node belonging to another workspace', function () {
    [$user, $workspace] = nodeWorkspace();
    $node = Node::factory()->custom()->create();

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/nodes/{$node->id}");

    $response->assertNotFound();
});
