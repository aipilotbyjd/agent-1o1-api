<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSkill;
use App\Models\Agents\AgentSkillReference;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

it('syncs skills onto an agent via the API', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $skill = AgentSkill::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))
        ->putJson("/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/skills", [
            'skill_ids' => [$skill->id],
        ])->assertOk()->assertJsonCount(1, 'data');

    expect($agent->skills()->count())->toBe(1);
});

it('folds attached skills and their references into agent instructions', function () {
    $agent = Agent::factory()->create(['instructions' => 'Base instructions.']);
    $skill = AgentSkill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Refunds', 'instructions' => 'Refund within 30 days.']);
    AgentSkillReference::factory()->create(['skill_id' => $skill->id, 'title' => 'Policy', 'content' => 'See docs.']);
    $agent->skills()->attach($skill->id);

    $instructions = (new WorkspaceAgent($agent))->instructions();

    expect($instructions)->toContain('Base instructions.')
        ->and($instructions)->toContain('Refunds')
        ->and($instructions)->toContain('Refund within 30 days.')
        ->and($instructions)->toContain('Policy: See docs.');
});
