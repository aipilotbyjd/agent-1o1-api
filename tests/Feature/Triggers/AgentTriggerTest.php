<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Runs\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function agentTriggerSetup(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    return [$admin, $workspace, $agent];
}

it('creates a webhook trigger for an agent with a message template', function () {
    [$admin, $workspace, $agent] = agentTriggerSetup();

    $response = $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/triggers",
        ['type' => 'webhook', 'config' => ['message' => 'Summarize: {{ input.text }}']],
    );

    $response->assertCreated();
    expect($response->json('data.webhook_url'))->toContain('/api/v1/hooks/');
});

it('runs an agent from a public webhook using the message template', function () {
    WorkspaceAgent::fake(['Here is the summary.']);
    [, $workspace, $agent] = agentTriggerSetup();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_type' => $agent->getMorphClass(),
        'triggerable_id' => $agent->id,
        'config' => ['message' => 'Summarize: {{ input.text }}'],
    ]);

    $response = $this->postJson("/api/v1/hooks/{$trigger->token}", ['text' => 'A very long article']);

    $response->assertStatus(202);

    $run = Run::find($response->json('data.run_id'));
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->trigger_type)->toBe('webhook')
        ->and($run->runnable_id)->toBe($agent->id)
        ->and($run->input['message'])->toBe('Summarize: A very long article')
        ->and($run->output['reply'])->toBe('Here is the summary.')
        ->and($run->triggered_by)->toBeNull()
        ->and($run->steps()->first()->usage)->toBeArray();
});

it('falls back to the payload message when no template is set', function () {
    WorkspaceAgent::fake(['Hello!']);
    [, $workspace, $agent] = agentTriggerSetup();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_type' => $agent->getMorphClass(),
        'triggerable_id' => $agent->id,
        'config' => null,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['message' => 'Hi agent'])->assertStatus(202);

    expect(Run::query()->latest('id')->first()->input['message'])->toBe('Hi agent');
});

it('runs an agent on a schedule', function () {
    WorkspaceAgent::fake(['Daily digest done.']);
    [, $workspace, $agent] = agentTriggerSetup();
    Trigger::factory()->schedule('* * * * *')->create([
        'workspace_id' => $workspace->id,
        'triggerable_type' => $agent->getMorphClass(),
        'triggerable_id' => $agent->id,
        'config' => ['cron' => '* * * * *', 'message' => 'Produce the daily digest.'],
    ]);

    $this->artisan('triggers:fire-due-schedule')->assertSuccessful();

    $run = Run::query()->latest('id')->first();
    expect($run)->not->toBeNull()
        ->and($run->trigger_type)->toBe('schedule')
        ->and($run->runnable_id)->toBe($agent->id)
        ->and($run->status)->toBe(RunStatus::Completed);
});

it('returns 409 for a webhook pointing at a deleted agent', function () {
    [, $workspace, $agent] = agentTriggerSetup();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_type' => $agent->getMorphClass(),
        'triggerable_id' => $agent->id,
    ]);
    $agent->delete();

    $this->postJson("/api/v1/hooks/{$trigger->token}")->assertStatus(409);
});

it('forbids a regular member from creating agent triggers', function () {
    [, $workspace, $agent] = agentTriggerSetup();
    $member = User::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $this->withToken(authHeader($member))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/triggers",
        ['type' => 'webhook'],
    )->assertForbidden();
});

it('lists and deletes agent triggers', function () {
    [$admin, $workspace, $agent] = agentTriggerSetup();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_type' => $agent->getMorphClass(),
        'triggerable_id' => $agent->id,
    ]);

    $this->withToken(authHeader($admin))->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/triggers",
    )->assertOk()->assertJsonCount(1, 'data');

    $this->withToken(authHeader($admin))->deleteJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/triggers/{$trigger->id}",
    )->assertOk();

    expect(Trigger::query()->count())->toBe(0);
});
