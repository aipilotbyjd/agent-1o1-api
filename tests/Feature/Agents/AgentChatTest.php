<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function chatSetup(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    return [$user, $workspace, $agent];
}

it('sends a message and records a completed run with usage', function () {
    WorkspaceAgent::fake(['Hello! How can I help?']);
    [$user, $workspace, $agent] = chatSetup();

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/chat",
        ['message' => 'Hi there'],
    );

    $response->assertOk()->assertJsonPath('data.reply', 'Hello! How can I help?');

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->workspace_id)->toBe($workspace->id)
        ->and($run->runnable_id)->toBe($agent->id)
        ->and($run->steps()->first()->status)->toBe(RunStepStatus::Completed)
        ->and($run->steps()->first()->usage)->toBeArray();
});

it('records a failed run when the agent errors', function () {
    WorkspaceAgent::fake()->preventStrayPrompts();
    [$user, $workspace, $agent] = chatSetup();

    // preventStrayPrompts with no queued responses throws inside the service.
    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/chat",
        ['message' => 'Hi there'],
    );

    $response->assertStatus(502);

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->not->toBeNull();
});

it('forbids a non-member from chatting', function () {
    WorkspaceAgent::fake(['Nope']);
    [, $workspace, $agent] = chatSetup();
    $outsider = User::factory()->create();

    $response = $this->withToken(authHeader($outsider))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/chat",
        ['message' => 'Hi'],
    );

    $response->assertForbidden();
});

it('requires a message', function () {
    WorkspaceAgent::fake(['Hi']);
    [$user, $workspace, $agent] = chatSetup();

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/chat",
        [],
    );

    $response->assertUnprocessable()->assertJsonValidationErrors('message');
});
