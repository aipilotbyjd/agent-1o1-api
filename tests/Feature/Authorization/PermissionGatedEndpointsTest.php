<?php

use App\Models\Agents\Agent;
use App\Models\Credentials\CredentialType;
use App\Models\Nodes\Node;
use App\Models\Nodes\NodeCategory;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function memberWithRole(Workspace $workspace, string $role): User
{
    $user = User::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return $user;
}

it('gates viewing content behind at least the viewer role', function (string $role, bool $allowed) {
    $workspace = Workspace::factory()->create();
    Agent::factory()->create(['workspace_id' => $workspace->id]);
    $user = memberWithRole($workspace, $role);

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/agents");

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'viewer can view' => ['viewer', true],
    'member can view' => ['member', true],
    'editor can view' => ['editor', true],
    'admin can view' => ['admin', true],
]);

it('gates content authoring behind at least the editor role', function (string $role, bool $allowed) {
    $workspace = Workspace::factory()->create();
    $user = memberWithRole($workspace, $role);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/nodes", [
        'category_id' => NodeCategory::factory()->create()->id,
        'step_type' => 'tool',
        'name' => 'My Node',
        'description' => 'A test node.',
        'icon' => 'bolt',
        'color' => '#000000',
        'config_schema' => ['type' => 'object', 'properties' => []],
        'config' => ['url' => 'https://example.com'],
    ]);

    $allowed ? $response->assertCreated() : $response->assertForbidden();
})->with([
    'viewer cannot create' => ['viewer', false],
    'member cannot create' => ['member', false],
    'editor can create' => ['editor', true],
    'admin can create' => ['admin', true],
]);

it('gates custom node deletion (editor-tier content management) behind at least the editor role', function (string $role, bool $allowed) {
    $workspace = Workspace::factory()->create();
    $node = Node::factory()->custom()->create(['workspace_id' => $workspace->id]);
    $user = memberWithRole($workspace, $role);

    $response = $this->withToken(authHeader($user))->deleteJson("/api/v1/workspaces/{$workspace->id}/nodes/{$node->id}");

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'viewer cannot delete' => ['viewer', false],
    'member cannot delete' => ['member', false],
    'editor can delete' => ['editor', true],
    'admin can delete' => ['admin', true],
]);

it('gates credential management behind at least the admin role, even for editors', function (string $role, bool $allowed) {
    $workspace = Workspace::factory()->create();
    $credentialType = CredentialType::factory()->create(['is_active' => true]);
    $user = memberWithRole($workspace, $role);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/credentials", [
        'name' => 'My Credential',
        'type' => $credentialType->key,
        'data' => ['key' => 'secret'],
    ]);

    $allowed ? $response->assertCreated() : $response->assertForbidden();
})->with([
    'viewer cannot create credentials' => ['viewer', false],
    'member cannot create credentials' => ['member', false],
    'editor cannot create credentials' => ['editor', false],
    'admin can create credentials' => ['admin', true],
]);
