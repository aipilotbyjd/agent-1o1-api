<?php

use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function analyticsSetup(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    return [$user, $workspace, $agent];
}

it('aggregates run totals, tokens, and latency for an agent', function () {
    [$user, $workspace, $agent] = analyticsSetup();

    $completed = Run::factory()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $agent->getMorphClass(),
        'runnable_id' => $agent->id,
        'status' => 'completed',
        'trigger_type' => 'manual',
        'started_at' => now()->subMinutes(2),
        'finished_at' => now()->subMinutes(2)->addSeconds(3),
    ]);
    $completed->steps()->create([
        'key' => 'chat', 'type' => 'agent', 'status' => 'completed',
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ]);

    Run::factory()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $agent->getMorphClass(),
        'runnable_id' => $agent->id,
        'status' => 'failed',
        'trigger_type' => 'webhook',
    ]);

    $response = $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/analytics",
    );

    $response->assertOk()
        ->assertJsonPath('data.totals.total_runs', 2)
        ->assertJsonPath('data.totals.completed', 1)
        ->assertJsonPath('data.totals.failed', 1)
        ->assertJsonPath('data.totals.success_rate', 0.5)
        ->assertJsonPath('data.tokens.total', 150)
        ->assertJsonPath('data.by_trigger_type.manual', 1)
        ->assertJsonPath('data.by_trigger_type.webhook', 1);

    expect($response->json('data.latency.max_duration_ms'))->toBeGreaterThan(0);
});

it('ignores runs outside the requested range', function () {
    [$user, $workspace, $agent] = analyticsSetup();

    $old = Run::factory()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $agent->getMorphClass(),
        'runnable_id' => $agent->id,
        'status' => 'completed',
    ]);
    $old->timestamps = false;
    $old->forceFill(['created_at' => now()->subDays(90)])->save();

    $response = $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/analytics",
    );

    $response->assertOk()->assertJsonPath('data.totals.total_runs', 0);
});

it('forbids a non-member from viewing analytics', function () {
    [, $workspace, $agent] = analyticsSetup();
    $outsider = User::factory()->create();

    $this->withToken(authHeader($outsider))->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/analytics",
    )->assertForbidden();
});
