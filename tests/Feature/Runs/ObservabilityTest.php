<?php

use App\Models\Nodes\Node;
use App\Models\Runs\ConnectorMetric;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\LogStreamingConfig;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\Runs\ConnectorMetricRecorder;
use App\Services\Workflows\Nodes\NodeResolver;
use Illuminate\Support\Facades\Http;

function obsSetup(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('rolls connector calls up into daily metrics', function () {
    [, $workspace] = obsSetup();
    $recorder = app(ConnectorMetricRecorder::class);

    $recorder->record($workspace->id, 'slack', true, 120);
    $recorder->record($workspace->id, 'slack', false, 300);

    $metric = ConnectorMetric::query()->where('connector', 'slack')->first();
    expect($metric->total_calls)->toBe(2)
        ->and($metric->success_calls)->toBe(1)
        ->and($metric->failed_calls)->toBe(1)
        ->and($metric->total_duration_ms)->toBe(420);
});

it('records a connector metric against the custom node that ran, not the http primitive', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true])]);
    [, $workspace] = obsSetup();
    $node = Node::factory()->custom()
        ->callingUrl('https://api.example.com/data')
        ->create(['workspace_id' => $workspace->id]);

    $run = Run::factory()->create(['workspace_id' => $workspace->id]);

    app(NodeResolver::class)->executable($node->type)->execute($run, [], []);

    $metric = ConnectorMetric::query()->where('workspace_id', $workspace->id)->first();

    expect($metric)->not->toBeNull()
        ->and($metric->connector)->toBe($node->type);
});

it('lists and summarizes connector metrics', function () {
    [$user, $workspace] = obsSetup();
    ConnectorMetric::factory()->create([
        'workspace_id' => $workspace->id, 'connector' => 'github',
        'total_calls' => 10, 'success_calls' => 8, 'failed_calls' => 2, 'total_duration_ms' => 5000,
    ]);

    $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/connector-metrics",
    )->assertOk()->assertJsonCount(1, 'data');

    $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/connector-metrics/summary",
    )->assertOk()
        ->assertJsonPath('data.0.connector', 'github')
        ->assertJsonPath('data.0.failure_rate', 0.2)
        ->assertJsonPath('data.0.avg_duration_ms', 500);
});

it('manages log streaming configs and tests delivery', function () {
    Http::fake(['logs.example.test/*' => Http::response(['ok' => true])]);
    [$user, $workspace] = obsSetup();

    $config = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/log-streaming-configs",
        ['destination' => 'webhook', 'endpoint' => 'https://logs.example.test/ingest'],
    )->assertCreated()->json('data');

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/log-streaming-configs/{$config['id']}/test",
    )->assertOk();

    expect(LogStreamingConfig::query()->find($config['id'])->last_delivered_at)->not->toBeNull();

    $this->withToken(authHeader($user))->deleteJson(
        "/api/v1/workspaces/{$workspace->id}/log-streaming-configs/{$config['id']}",
    )->assertOk();
});

it('reports a failed test delivery', function () {
    Http::fake(['logs.example.test/*' => Http::response('nope', 500)]);
    [$user, $workspace] = obsSetup();
    $config = LogStreamingConfig::factory()->create([
        'workspace_id' => $workspace->id,
        'endpoint' => 'https://logs.example.test/ingest',
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/log-streaming-configs/{$config->id}/test",
    )->assertStatus(502);
});

it('forbids an editor from managing log streaming configs', function () {
    [$user, $workspace] = obsSetup('editor');

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/log-streaming-configs",
        ['destination' => 'webhook', 'endpoint' => 'https://logs.example.test/ingest'],
    )->assertForbidden();
});

it('returns a workspace dashboard summary', function () {
    [$user, $workspace] = obsSetup();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    Run::factory()->completed()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
    ]);
    Run::factory()->failed()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
    ]);

    $response = $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/dashboard",
    );

    $response->assertOk()
        ->assertJsonPath('data.counts.workflows', 1)
        ->assertJsonPath('data.runs_last_30_days.total', 2)
        ->assertJsonPath('data.runs_last_30_days.failed', 1)
        ->assertJsonPath('data.top_workflows.0.workflow_id', $workflow->id)
        ->assertJsonPath('data.recent_failures.0.runnable_name', $workflow->name);
});

it('allows a viewer to see the dashboard', function () {
    [$user, $workspace] = obsSetup('viewer');

    $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/dashboard",
    )->assertOk();
});
