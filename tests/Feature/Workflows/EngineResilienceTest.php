<?php

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\Tool;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Http;

function resilienceWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

it('continues past a failed step when continue_on_error is set', function () {
    [$admin, $workspace] = resilienceWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $workflow->steps()->create([
        'key' => 'boom',
        'type' => 'agent',
        'config' => ['agent_id' => 999999, 'continue_on_error' => true],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->where('key', 'boom')->first()->status)->toBe(RunStepStatus::Failed);
});

it('does not follow an unconditional edge when the step failed', function () {
    [$admin, $workspace] = resilienceWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $boom = $workflow->steps()->create(['key' => 'boom', 'type' => 'agent', 'config' => ['agent_id' => 999999]]);
    $next = $workflow->steps()->create(['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => ['x' => '1']]]);
    $workflow->edges()->create(['from_step_id' => $boom->id, 'to_step_id' => $next->id]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->steps()->where('key', 'next')->first()?->status)->not->toBe(RunStepStatus::Completed);
});

it('does not follow an error edge when the step succeeded', function () {
    [$admin, $workspace] = resilienceWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $ok = $workflow->steps()->create(['key' => 'ok', 'type' => 'transform', 'config' => ['mapping' => ['x' => '1']]]);
    $handler = $workflow->steps()->create(['key' => 'handler', 'type' => 'transform', 'config' => ['mapping' => ['y' => '2']]]);
    $workflow->edges()->create(['from_step_id' => $ok->id, 'to_step_id' => $handler->id, 'condition' => 'error']);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->where('key', 'handler')->first()->status)->toBe(RunStepStatus::Skipped);
});

it('fails a step that overruns its configured timeout', function () {
    // A genuinely slow response, so the elapsed-time check is exercised for real.
    Http::fake(['api.example.com/*' => function () {
        usleep(1_100_000);

        return Http::response('ok', 200);
    }]);

    [$admin, $workspace] = resilienceWorkspace();
    $tool = Tool::factory()->create([
        'workspace_id' => $workspace->id,
        'config' => ['url' => 'https://api.example.com/slow', 'method' => 'GET', 'parameters' => []],
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'slow',
        'type' => 'tool',
        'config' => ['tool_id' => $tool->id, 'arguments' => [], 'timeout_seconds' => 1],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    $step = $run->steps()->where('key', 'slow')->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($step->status)->toBe(RunStepStatus::Failed)
        ->and($step->error)->toContain('timeout');
});

it('only injects the workspace variables a step actually references', function () {
    [$admin, $workspace] = resilienceWorkspace();
    Variable::factory()->create(['workspace_id' => $workspace->id, 'key' => 'used', 'value' => 'used-value']);
    Variable::factory()->secret()->create([
        'workspace_id' => $workspace->id,
        'key' => 'unused_secret',
        'value' => 'must-not-appear',
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'peek',
        'type' => 'transform',
        'config' => ['mapping' => [
            'used' => '{{ variables.used }}',
            'everything' => '{{ variables }}',
        ]],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $output = Run::query()->latest('id')->first()->steps()->where('key', 'peek')->first()->output;

    expect($output['used'])->toBe('used-value')
        ->and(json_encode($output))->not->toContain('must-not-appear');
});

it('cancels a running run and its in-flight steps', function () {
    [$admin, $workspace] = resilienceWorkspace();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => '1']]]);
    $workflow->publishVersion();

    $run = Run::create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'workflow_version_id' => $workflow->current_version_id,
        'trigger_type' => 'manual',
        'input' => [],
    ]);
    $run->markRunning();
    $run->steps()->create(['key' => 'a', 'type' => 'transform', 'input' => []]);

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/cancel",
    )->assertOk();

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled)
        ->and($run->steps()->where('key', 'a')->first()->status)->toBe(RunStepStatus::Cancelled);
});

describe('condition operators', function () {
    beforeEach(function (): void {
        [$this->admin, $this->workspace] = resilienceWorkspace();
    });

    function runCondition(Workspace $workspace, User $admin, array $config, array $input): string
    {
        $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
        $workflow->steps()->create(['key' => 'check', 'type' => 'condition', 'config' => $config]);
        $workflow->publishVersion();

        test()->withToken(authHeader($admin))->postJson(
            "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
            ['input' => $input],
        )->assertCreated();

        return Run::query()->latest('id')->first()->steps()->where('key', 'check')->first()->output['result'];
    }

    it('evaluates in and not_in', function () {
        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.role }}', 'operator' => 'in', 'value' => 'admin, owner',
        ], ['role' => 'owner']))->toBe('true');

        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.role }}', 'operator' => 'not_in', 'value' => 'admin, owner',
        ], ['role' => 'guest']))->toBe('true');
    });

    it('evaluates exists and missing', function () {
        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.nope }}', 'operator' => 'missing', 'value' => '',
        ], []))->toBe('true');

        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.here }}', 'operator' => 'exists', 'value' => '',
        ], ['here' => 'yes']))->toBe('true');
    });

    it('evaluates a regex match', function () {
        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.email }}', 'operator' => 'matches', 'value' => '^\w+@example\.com$',
        ], ['email' => 'ada@example.com']))->toBe('true');
    });

    it('does not treat a non-numeric string as zero in a numeric comparison', function () {
        // "abc" > "-1" would be true if the operand were cast blindly to a float.
        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.value }}', 'operator' => 'gt', 'value' => '-1',
        ], ['value' => 'abc']))->toBe('false');
    });

    it('compares numbers numerically rather than as strings', function () {
        expect(runCondition($this->workspace, $this->admin, [
            'field' => '{{ input.value }}', 'operator' => 'gt', 'value' => '9',
        ], ['value' => 10]))->toBe('true');
    });
});
