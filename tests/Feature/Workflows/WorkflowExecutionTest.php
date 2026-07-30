<?php

use App\Ai\Agents\WorkspaceAgent;
use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Exceptions\Workflows\InvalidGraphException;
use App\Models\Agents\Agent;
use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Http;

function executionWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

function buildGraph(Workflow $workflow, array $steps, array $edges): void
{
    $byKey = [];

    foreach ($steps as $step) {
        $byKey[$step['key']] = $workflow->steps()->create($step);
    }

    foreach ($edges as [$from, $to, $condition]) {
        $workflow->edges()->create([
            'from_step_id' => $byKey[$from]->id,
            'to_step_id' => $byKey[$to]->id,
            'condition' => $condition,
        ]);
    }

    $workflow->publishVersion();
}

it('runs a linear agent-then-transform workflow to completion', function () {
    WorkspaceAgent::fake(['This is the summary.']);
    [$user, $workspace] = executionWorkspace();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    buildGraph($workflow, [
        ['key' => 'summarize', 'type' => 'agent', 'config' => ['agent_id' => $agent->id, 'prompt' => 'Summarize: {{ input.message }}']],
        ['key' => 'format', 'type' => 'transform', 'config' => ['mapping' => ['summary' => 'Result: {{ steps.summarize.text }}']]],
    ], [
        ['summarize', 'format', null],
    ]);

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['message' => 'Long ticket text']],
    );

    $response->assertCreated();

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe(['summary' => 'Result: This is the summary.'])
        ->and($run->steps()->count())->toBe(2)
        ->and($run->steps()->where('key', 'summarize')->first()->usage)->toBeArray();
});

it('follows only the matching condition branch', function () {
    [$user, $workspace] = executionWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    buildGraph($workflow, [
        ['key' => 'check', 'type' => 'condition', 'config' => ['field' => '{{ input.amount }}', 'operator' => 'gt', 'value' => '100']],
        ['key' => 'big', 'type' => 'transform', 'config' => ['mapping' => ['path' => 'big']]],
        ['key' => 'small', 'type' => 'transform', 'config' => ['mapping' => ['path' => 'small']]],
    ], [
        ['check', 'big', 'true'],
        ['check', 'small', 'false'],
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['amount' => 250]],
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    // The excluded branch is recorded as skipped rather than left absent, so a merge
    // downstream can tell "will never arrive" apart from "has not arrived yet".
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe(['path' => 'big'])
        ->and($run->steps()->where('key', 'big')->first()->status)->toBe(RunStepStatus::Completed)
        ->and($run->steps()->where('key', 'small')->first()->status)->toBe(RunStepStatus::Skipped);
});

it('executes a tool step with templated arguments', function () {
    Http::fake(['api.example.com/*' => Http::response('found it', 200)]);
    [$user, $workspace] = executionWorkspace();
    $node = Node::factory()->custom()->create(['workspace_id' => $workspace->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    buildGraph($workflow, [
        ['key' => 'lookup', 'type' => 'tool', 'config' => ['node' => $node->type, 'query' => '{{ input.order }}']],
    ], []);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['order' => 'ORD-77']],
    )->assertCreated();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'query=ORD-77'));

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Completed);
});

it('marks the run failed when a step throws', function () {
    Http::fake(fn () => throw new Exception('Connection refused'));
    [$user, $workspace] = executionWorkspace();
    $node = Node::factory()->custom()->create(['workspace_id' => $workspace->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    buildGraph($workflow, [
        ['key' => 'lookup', 'type' => 'tool', 'config' => ['node' => $node->type]],
        ['key' => 'after', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'y']]],
    ], [
        ['lookup', 'after', null],
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('lookup')
        ->and($run->steps()->where('key', 'lookup')->first()->status)->toBe(RunStepStatus::Failed)
        ->and($run->steps()->where('key', 'after')->exists())->toBeFalse();
});

it('refuses to publish a workflow with no steps', function () {
    [$user, $workspace] = executionWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    expect(fn () => $workflow->publishVersion())
        ->toThrow(InvalidGraphException::class, 'The graph has no steps.');
});

it('fails a run whose pinned version has no steps', function () {
    [$user, $workspace] = executionWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    // Versions published before graph validation existed can still be empty, so the
    // engine keeps its own guard rather than trusting every stored version.
    $version = $workflow->versions()->create([
        'version' => 1,
        'graph' => ['steps' => [], 'edges' => []],
    ]);
    $workflow->update(['current_version_id' => $version->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    expect(Run::query()->latest('id')->first()->status)->toBe(RunStatus::Failed);
});
