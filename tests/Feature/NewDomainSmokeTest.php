<?php

use App\Models\Agents\Agent;
use App\Models\Agents\AgentSkill;
use App\Models\Runs\Run;
use App\Models\Runs\RunLog;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowShare;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function newDomainWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('creates and lists agent knowledge', function () {
    [$user, $workspace] = newDomainWorkspace();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge", [
            'title' => 'Refund policy',
            'content' => 'Refunds within 30 days.',
        ])->assertCreated();

    $this->withToken(authHeader($user))
        ->getJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/knowledge")
        ->assertOk()->assertJsonCount(1, 'data');
});

it('creates an agent skill and rejects a duplicate slug in the same workspace', function () {
    [$user, $workspace] = newDomainWorkspace();

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/agent-skills", [
            'name' => 'Refund handling',
            'instructions' => 'Follow the refund policy.',
        ])->assertCreated();

    AgentSkill::factory()->create(['workspace_id' => $workspace->id, 'slug' => 'billing-help']);

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/agent-skills", [
            'name' => 'Billing Help',
            'slug' => 'billing-help',
            'instructions' => 'Duplicate slug.',
        ])->assertUnprocessable();
});

it('paginates run logs for a run', function () {
    [$user, $workspace] = newDomainWorkspace('member');
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'trigger_type' => 'manual',
        'input' => [],
    ]);
    RunLog::factory()->create(['run_id' => $run->id, 'workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->getJson("/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/logs")
        ->assertOk()->assertJsonCount(1, 'data');
});

it('creates a workspace environment and rejects a duplicate slug', function () {
    [$user, $workspace] = newDomainWorkspace();

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/environments", [
            'name' => 'Staging',
            'slug' => 'staging',
        ])->assertCreated();

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/environments", [
            'name' => 'Staging Again',
            'slug' => 'staging',
        ])->assertUnprocessable();
});

it('requests and approves a workflow approval', function () {
    [$user, $workspace] = newDomainWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    // Approving publishes the graph, which must be valid to publish.
    $workflow->replaceGraph([['key' => 's1', 'type' => 'delay', 'config' => [], 'position' => null]], []);

    $response = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/approvals", []);

    $response->assertCreated();
    $approvalId = $response->json('data.id');

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/approvals/{$approvalId}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');
});

it('exposes a shared workflow publicly without workspace info', function () {
    [, $workspace] = newDomainWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $workflow->replaceGraph([['key' => 's1', 'type' => 'delay', 'config' => [], 'position' => null]], []);
    $workflow->publishVersion();

    $share = WorkflowShare::factory()->create(['workflow_id' => $workflow->id, 'workspace_id' => $workspace->id]);

    $response = $this->getJson("/api/v1/shared-workflows/{$share->token}");

    $response->assertOk()->assertJsonMissingPath('data.workspace_id');
});

it('creates a workflow builder session and posts a message', function () {
    [$user, $workspace] = newDomainWorkspace('member');

    $response = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions", [
            'title' => 'New automation',
        ]);
    $response->assertCreated();
    $sessionId = $response->json('data.id');

    $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflow-builder-sessions/{$sessionId}/messages", [
            'content' => 'Add a delay step.',
        ])->assertCreated()->assertJsonPath('data.processing_status', 'pending');
});
