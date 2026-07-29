<?php

use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

it('aggregates token usage for a workspace', function () {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    $run = Run::factory()->completed()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $agent->getMorphClass(),
        'runnable_id' => $agent->id,
    ]);
    RunStep::factory()->completed()->create([
        'run_id' => $run->id,
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ]);
    RunStep::factory()->completed()->create([
        'run_id' => $run->id,
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    // Usage in another workspace must not leak in.
    RunStep::factory()->completed()->create(['usage' => ['prompt_tokens' => 999, 'completion_tokens' => 999]]);

    $response = $this->withToken(authHeader($admin))->getJson("/api/v1/workspaces/{$workspace->id}/usage");

    $response->assertOk()
        ->assertJsonPath('data.totals.prompt_tokens', 110)
        ->assertJsonPath('data.totals.completion_tokens', 55)
        ->assertJsonPath('data.totals.total_tokens', 165)
        ->assertJsonPath('data.totals.steps', 2)
        ->assertJsonPath('data.by_runnable.agent:'.$agent->id, 165);
});

it('filters usage by date range', function () {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    $run = Run::factory()->completed()->create(['workspace_id' => $workspace->id]);
    $old = RunStep::factory()->completed()->create([
        'run_id' => $run->id,
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 0],
    ]);
    $old->timestamps = false;
    $old->forceFill(['created_at' => now()->subDays(30)])->save();

    RunStep::factory()->completed()->create([
        'run_id' => $run->id,
        'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 3],
    ]);

    $from = now()->subDay()->toDateString();

    $response = $this->withToken(authHeader($admin))->getJson(
        "/api/v1/workspaces/{$workspace->id}/usage?from={$from}",
    );

    $response->assertOk()->assertJsonPath('data.totals.total_tokens', 10);
});

it('forbids regular members from viewing usage', function () {
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $this->withToken(authHeader($member))->getJson("/api/v1/workspaces/{$workspace->id}/usage")
        ->assertForbidden();
});
