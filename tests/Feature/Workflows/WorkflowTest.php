<?php

use App\Models\Agents\Agent;
use App\Models\Nodes\Node;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowStep;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function workflowWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('creates a workflow as draft', function () {
    [$user, $workspace] = workflowWorkspace();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/workflows", [
        'name' => 'Ticket Triage',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'ticket-triage')
        ->assertJsonPath('data.status', 'draft');
});

it('forbids a regular member from creating a workflow', function () {
    [$user, $workspace] = workflowWorkspace('member');

    $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/workflows", [
        'name' => 'Nope',
    ])->assertForbidden();
});

it('saves a workflow graph', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [
                ['key' => 'summarize', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'Summarize: {{ input.message }}']],
                ['key' => 'format', 'type' => 'transform', 'config' => ['mapping' => ['summary' => '{{ steps.summarize.text }}']]],
            ],
            'edges' => [
                ['from' => 'summarize', 'to' => 'format'],
            ],
        ],
    );

    $response->assertOk()
        ->assertJsonCount(2, 'data.steps')
        ->assertJsonPath('data.edges.0.from', 'summarize');
});

it('saves a tool step that names a workspace custom node and links it to that row', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $node = Node::factory()->custom()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [['key' => 'lookup', 'type' => 'tool', 'config' => ['node' => $node->type]]],
            'edges' => [],
        ],
    )->assertOk();

    expect($workflow->steps()->where('key', 'lookup')->first()->node_id)->toBe($node->id);
});

it('rejects a tool step that names no node', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [['key' => 'lookup', 'type' => 'tool', 'config' => []]],
            'edges' => [],
        ],
    )->assertStatus(422);
});

it('rejects a graph with an edge to an unknown step', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
            'edges' => [['from' => 'a', 'to' => 'ghost']],
        ],
    );

    $response->assertUnprocessable()->assertJsonValidationErrors('edges.0.to');
});

it('rejects a graph with an agent from another workspace', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $foreignAgent = Agent::factory()->create();

    $response = $this->withToken(authHeader($user))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/graph",
        [
            'steps' => [['key' => 'a', 'type' => 'agent', 'config' => ['agent_id' => $foreignAgent->id]]],
            'edges' => [],
        ],
    );

    $response->assertUnprocessable();
});

it('refuses to publish an empty workflow', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    // Graph problems come back as validation errors, keyed so the builder can surface
    // each issue against the step it belongs to.
    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/publish",
    )
        ->assertUnprocessable()
        ->assertJsonPath('errors.graph.0', 'The graph has no steps.');
});

it('publishes a workflow with steps', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    WorkflowStep::factory()->create(['workflow_id' => $workflow->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/publish",
    )->assertOk()->assertJsonPath('data.status', 'published');
});

it('refuses to trigger a draft workflow', function () {
    [$user, $workspace] = workflowWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    WorkflowStep::factory()->create(['workflow_id' => $workflow->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertStatus(400);
});
