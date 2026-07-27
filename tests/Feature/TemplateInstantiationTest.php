<?php

use App\Models\Agents\Agent;
use App\Models\Agents\AgentTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowTemplate;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function templateWorkspace(string $role = 'member'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('instantiates an agent from a template into the target workspace', function () {
    [$user, $workspace] = templateWorkspace();
    $template = AgentTemplate::factory()->create([
        'system_prompt' => 'You handle refunds.',
        'llm_provider' => 'anthropic',
        'llm_model' => 'claude-sonnet-4-6',
        'usage_count' => 0,
    ]);

    $response = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/agent-templates/{$template->id}/instantiate");

    $response->assertCreated()
        ->assertJsonPath('data.instructions', 'You handle refunds.')
        ->assertJsonPath('data.provider', 'anthropic')
        ->assertJsonPath('data.model', 'claude-sonnet-4-6');

    expect(Agent::where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($template->fresh()->usage_count)->toBe(1);
});

it('instantiates a workflow from a template with the template graph as the live draft', function () {
    [$user, $workspace] = templateWorkspace();
    $graph = [
        'steps' => [['key' => 's1', 'type' => 'delay', 'config' => ['seconds' => 5], 'position' => null]],
        'edges' => [],
    ];
    $template = WorkflowTemplate::factory()->create(['graph' => $graph, 'usage_count' => 0]);

    $response = $this->withToken(authHeader($user))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflow-templates/{$template->id}/instantiate");

    $response->assertCreated();

    $workflow = Workflow::where('workspace_id', $workspace->id)->firstOrFail();
    expect($workflow->graphArray()['steps'])->toHaveCount(1)
        ->and($workflow->graphArray()['steps'][0]['key'])->toBe('s1')
        ->and($template->fresh()->usage_count)->toBe(1);
});

it('forbids a non-member from instantiating either template type', function () {
    $workspace = Workspace::factory()->create();
    $outsider = User::factory()->create();
    $agentTemplate = AgentTemplate::factory()->create();
    $workflowTemplate = WorkflowTemplate::factory()->create();

    $this->withToken(authHeader($outsider))
        ->postJson("/api/v1/workspaces/{$workspace->id}/agent-templates/{$agentTemplate->id}/instantiate")
        ->assertForbidden();

    $this->withToken(authHeader($outsider))
        ->postJson("/api/v1/workspaces/{$workspace->id}/workflow-templates/{$workflowTemplate->id}/instantiate")
        ->assertForbidden();
});
