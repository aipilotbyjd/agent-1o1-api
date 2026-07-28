<?php

use App\Ai\Agents\StepDiagnosisAgent;
use App\Jobs\Runs\DiagnoseFailedRunStep;
use App\Models\Runs\Run;
use App\Models\Runs\RunFixSuggestion;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\AiGenerationLog;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Queue;

function fixSetup(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->failed()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
    ]);

    return [$user, $workspace, $workflow, $run];
}

it('queues a diagnosis for a failed step', function () {
    Queue::fake();
    [$user, $workspace, , $run] = fixSetup();
    $run->steps()->create(['key' => 'fetch', 'type' => 'tool', 'status' => 'failed', 'error' => 'timeout']);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/fix-suggestions/diagnose",
        ['step_key' => 'fetch'],
    )->assertStatus(202);

    Queue::assertPushed(DiagnoseFailedRunStep::class);
});

it('rejects diagnosing a step that did not fail', function () {
    [$user, $workspace, , $run] = fixSetup();
    $run->steps()->create(['key' => 'ok', 'type' => 'tool', 'status' => 'completed']);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/fix-suggestions/diagnose",
        ['step_key' => 'ok'],
    )->assertUnprocessable();
});

it('creates a fix suggestion and generation log from the diagnosis job', function () {
    StepDiagnosisAgent::fake([json_encode([
        'diagnosis' => 'The HTTP request timed out.',
        'suggestions' => [
            ['title' => 'Raise timeout', 'description' => 'Increase to 30s.', 'fix_config' => ['timeout' => 30]],
        ],
    ])]);

    [, $workspace, $workflow, $run] = fixSetup();
    $workflow->steps()->create(['key' => 'fetch', 'type' => 'tool', 'config' => ['timeout' => 5]]);
    $run->steps()->create(['key' => 'fetch', 'type' => 'tool', 'status' => 'failed', 'error' => 'timeout']);

    (new DiagnoseFailedRunStep($run->id, 'fetch'))->handle();

    $suggestion = RunFixSuggestion::query()->first();
    expect($suggestion)->not->toBeNull()
        ->and($suggestion->diagnosis)->toBe('The HTTP request timed out.')
        ->and($suggestion->suggestions[0]['fix_config'])->toBe(['timeout' => 30])
        ->and(AiGenerationLog::query()->where('type', 'autofix')->count())->toBe(1);
});

it('applies a fix to the workflow draft step config', function () {
    [$user, $workspace, $workflow, $run] = fixSetup();
    $step = $workflow->steps()->create(['key' => 'fetch', 'type' => 'tool', 'config' => ['timeout' => 5, 'url' => 'https://x.test']]);
    $suggestion = RunFixSuggestion::factory()->create([
        'run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'step_key' => 'fetch',
        'suggestions' => [['title' => 'Raise timeout', 'description' => 'x', 'fix_config' => ['timeout' => 30]]],
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/fix-suggestions/{$suggestion->id}/apply",
        ['suggestion_index' => 0],
    )->assertOk()->assertJsonPath('data.status', 'applied');

    expect($step->fresh()->config)->toBe(['timeout' => 30, 'url' => 'https://x.test'])
        ->and($workflow->fresh()->has_unpublished_changes)->toBeTrue();
});

it('rejects applying a suggestion without a fix config', function () {
    [$user, $workspace, $workflow, $run] = fixSetup();
    $workflow->steps()->create(['key' => 'fetch', 'type' => 'tool', 'config' => []]);
    $suggestion = RunFixSuggestion::factory()->create([
        'run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'step_key' => 'fetch',
        'suggestions' => [['title' => 'Manual fix', 'description' => 'x', 'fix_config' => []]],
    ]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/fix-suggestions/{$suggestion->id}/apply",
        ['suggestion_index' => 0],
    )->assertUnprocessable();
});

it('dismisses a suggestion', function () {
    [$user, $workspace, , $run] = fixSetup();
    $suggestion = RunFixSuggestion::factory()->create(['run_id' => $run->id, 'workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/fix-suggestions/{$suggestion->id}/dismiss",
    )->assertOk();

    expect($suggestion->fresh()->status)->toBe(RunFixSuggestion::STATUS_DISMISSED);
});

it('forbids a viewer from diagnosing', function () {
    [$user, $workspace, , $run] = fixSetup('viewer');
    $run->steps()->create(['key' => 'fetch', 'type' => 'tool', 'status' => 'failed']);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/fix-suggestions/diagnose",
        ['step_key' => 'fetch'],
    )->assertForbidden();
});

it('lists ai generation logs for admins', function () {
    [$user, $workspace] = fixSetup();
    AiGenerationLog::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    AiGenerationLog::factory()->create();

    $this->withToken(authHeader($user))->getJson(
        "/api/v1/workspaces/{$workspace->id}/ai-generation-logs",
    )->assertOk()->assertJsonCount(2, 'data.data');
});
