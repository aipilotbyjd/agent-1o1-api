<?php

use App\Ai\Agents\EvalJudgeAgent;
use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function evalSetup(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    return [$user, $workspace, $agent];
}

it('creates an eval suite with cases', function () {
    [$user, $workspace, $agent] = evalSetup();

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites",
        [
            'name' => 'Refund policy checks',
            'cases' => [
                [
                    'name' => 'mentions refund',
                    'input' => 'Can I get my money back?',
                    'assertions' => [['type' => 'contains', 'value' => 'refund']],
                ],
            ],
        ],
    );

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Refund policy checks')
        ->assertJsonCount(1, 'data.cases');
});

it('rejects an unknown assertion type', function () {
    [$user, $workspace, $agent] = evalSetup();

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites",
        [
            'name' => 'Bad suite',
            'cases' => [
                ['name' => 'x', 'input' => 'y', 'assertions' => [['type' => 'sentiment', 'value' => 'z']]],
            ],
        ],
    )->assertUnprocessable();
});

it('forbids a viewer from creating eval suites', function () {
    [$user, $workspace, $agent] = evalSetup('viewer');

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites",
        ['name' => 'Nope'],
    )->assertForbidden();
});

it('runs a suite and grades deterministic assertions', function () {
    WorkspaceAgent::fake(['You are eligible for a full refund within 30 days.']);
    [$user, $workspace, $agent] = evalSetup();

    $suite = AgentEvalSuite::factory()->create(['agent_id' => $agent->id, 'workspace_id' => $workspace->id]);
    AgentEvalCase::factory()->create([
        'suite_id' => $suite->id,
        'input' => 'Can I get my money back?',
        'assertions' => [
            ['type' => 'contains', 'value' => 'refund'],
            ['type' => 'not_contains', 'value' => 'no refunds'],
        ],
    ]);

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/run",
    );

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.passed', 1)
        ->assertJsonPath('data.failed', 0);

    expect(AgentEvalRun::query()->count())->toBe(1);
});

it('marks a case failed when an assertion does not hold', function () {
    WorkspaceAgent::fake(['Sorry, we do not offer that.']);
    [$user, $workspace, $agent] = evalSetup();

    $suite = AgentEvalSuite::factory()->create(['agent_id' => $agent->id, 'workspace_id' => $workspace->id]);
    AgentEvalCase::factory()->create([
        'suite_id' => $suite->id,
        'assertions' => [['type' => 'contains', 'value' => 'refund']],
    ]);

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/run",
    );

    $response->assertOk()
        ->assertJsonPath('data.passed', 0)
        ->assertJsonPath('data.failed', 1);

    expect($response->json('data.results.0.failures.0'))->toContain('refund');
});

it('grades an llm_rubric assertion with the judge agent', function () {
    WorkspaceAgent::fake(['We cannot help with that, but here is an alternative.']);
    EvalJudgeAgent::fake(['{"passed": true, "reason": "Politely declines with alternative."}']);
    [$user, $workspace, $agent] = evalSetup();

    $suite = AgentEvalSuite::factory()->create(['agent_id' => $agent->id, 'workspace_id' => $workspace->id]);
    AgentEvalCase::factory()->create([
        'suite_id' => $suite->id,
        'assertions' => [['type' => 'llm_rubric', 'value' => 'Politely declines and offers an alternative.']],
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/run",
    )->assertOk()->assertJsonPath('data.passed', 1);
});

it('refuses to run an empty suite', function () {
    [$user, $workspace, $agent] = evalSetup();
    $suite = AgentEvalSuite::factory()->create(['agent_id' => $agent->id, 'workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/run",
    )->assertUnprocessable();
});

it('404s a suite accessed through another agent', function () {
    [$user, $workspace, $agent] = evalSetup();
    $otherAgent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $suite = AgentEvalSuite::factory()->create(['agent_id' => $otherAgent->id, 'workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}",
    )->assertNotFound();
});

it('adds and deletes cases on a suite', function () {
    [$user, $workspace, $agent] = evalSetup();
    $suite = AgentEvalSuite::factory()->create(['agent_id' => $agent->id, 'workspace_id' => $workspace->id]);

    $case = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/cases",
        ['name' => 'new case', 'input' => 'hello', 'assertions' => [['type' => 'contains', 'value' => 'hi']]],
    )->assertCreated()->json('data');

    $this->withToken(authHeader($user))->deleteJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/eval-suites/{$suite->id}/cases/{$case['id']}",
    )->assertOk();

    expect($suite->cases()->count())->toBe(0);
});
