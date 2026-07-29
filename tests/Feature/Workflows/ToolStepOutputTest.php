<?php

use App\Enums\Runs\RunStatus;
use App\Models\Runs\Run;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Http;

function toolOutputWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

it('exposes an http tool response as structured output a later step can read into', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['items' => [['id' => 42, 'name' => 'Ada']]], 200),
    ]);

    [$admin, $workspace] = toolOutputWorkspace();

    $tool = Tool::factory()->create([
        'workspace_id' => $workspace->id,
        'config' => ['url' => 'https://api.example.com/lookup', 'method' => 'GET', 'parameters' => []],
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    $fetch = $workflow->steps()->create([
        'key' => 'fetch',
        'type' => 'tool',
        'config' => ['tool_id' => $tool->id, 'arguments' => []],
    ]);
    $use = $workflow->steps()->create([
        'key' => 'use_it',
        'type' => 'transform',
        'config' => ['mapping' => [
            'first_id' => '{{ steps.fetch.json.items.0.id }}',
            'first_name' => '{{ steps.fetch.json.items.0.name }}',
            'status' => '{{ steps.fetch.status }}',
        ]],
    ]);
    $workflow->edges()->create(['from_step_id' => $fetch->id, 'to_step_id' => $use->id]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();
    $fetchStep = $run->steps()->where('key', 'fetch')->first();
    $useStep = $run->steps()->where('key', 'use_it')->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($fetchStep->output['ok'])->toBeTrue()
        ->and($fetchStep->output['status'])->toBe(200)
        ->and($fetchStep->output['json']['items'][0]['id'])->toBe(42)
        ->and($useStep->output['first_id'])->toBe(42)
        ->and($useStep->output['first_name'])->toBe('Ada')
        ->and($useStep->output['status'])->toBe(200);
});

it('reports a non-2xx response as a structured failure without throwing', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['error' => 'nope'], 422),
    ]);

    [$admin, $workspace] = toolOutputWorkspace();

    $tool = Tool::factory()->create([
        'workspace_id' => $workspace->id,
        'config' => ['url' => 'https://api.example.com/lookup', 'method' => 'GET', 'parameters' => []],
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'fetch',
        'type' => 'tool',
        'config' => ['tool_id' => $tool->id, 'arguments' => []],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $step = Run::query()->latest('id')->first()->steps()->where('key', 'fetch')->first();

    expect($step->output['ok'])->toBeFalse()
        ->and($step->output['status'])->toBe(422)
        ->and($step->output['json']['error'])->toBe('nope');
});

it('sends typed json arguments rather than stringified ones', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

    [$admin, $workspace] = toolOutputWorkspace();

    $tool = Tool::factory()->create([
        'workspace_id' => $workspace->id,
        'config' => ['url' => 'https://api.example.com/create', 'method' => 'POST', 'parameters' => []],
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'create',
        'type' => 'tool',
        'config' => ['tool_id' => $tool->id, 'arguments' => [
            'count' => '{{ input.count }}',
            'active' => '{{ input.active }}',
            'nested' => ['label' => '{{ input.label }}'],
        ]],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
        ['input' => ['count' => 3, 'active' => true, 'label' => 'hi']],
    )->assertCreated();

    Http::assertSent(fn ($request) => $request->data() === [
        'count' => 3,
        'active' => true,
        'nested' => ['label' => 'hi'],
    ]);
});

it('refuses to call a private network address', function () {
    [$admin, $workspace] = toolOutputWorkspace();

    $tool = Tool::factory()->create([
        'workspace_id' => $workspace->id,
        'config' => ['url' => 'http://127.0.0.1/admin', 'method' => 'GET', 'parameters' => []],
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'fetch',
        'type' => 'tool',
        'config' => ['tool_id' => $tool->id, 'arguments' => []],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $run = Run::query()->latest('id')->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('blocked');
});
