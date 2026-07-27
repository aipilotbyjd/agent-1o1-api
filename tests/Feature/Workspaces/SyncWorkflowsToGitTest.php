<?php

use App\Jobs\SyncWorkflowsToGit;
use App\Models\GitSyncConfig;
use App\Models\Workflow;
use Illuminate\Support\Facades\Http;

function githubFakeUrl(GitSyncConfig $config, Workflow $workflow): string
{
    return "https://api.github.com/repos/{$config->repository}/contents/{$config->base_path}/{$workflow->slug}.json*";
}

it('puts a file per published workflow and records last_synced_at', function () {
    $config = GitSyncConfig::factory()->create(['repository' => 'acme/workflows', 'base_path' => 'workflows']);
    $workflow = Workflow::factory()->create(['workspace_id' => $config->workspace_id]);
    $workflow->replaceGraph([['key' => 's1', 'type' => 'delay', 'config' => [], 'position' => null]], []);
    $workflow->publishVersion();
    $workflow->update(['status' => 'published']);

    Http::fake([
        githubFakeUrl($config, $workflow) => Http::sequence()
            ->push(['message' => 'Not Found'], 404)
            ->push(['content' => base64_encode('{}'), 'sha' => 'abc123'], 200),
    ]);

    (new SyncWorkflowsToGit($config->id))->handle();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), "{$workflow->slug}.json")
        && ! isset($request->data()['sha']));

    expect($config->fresh()->last_synced_at)->not->toBeNull();
});

it('includes the existing sha when updating a file that already exists', function () {
    $config = GitSyncConfig::factory()->create(['repository' => 'acme/workflows', 'base_path' => 'workflows']);
    $workflow = Workflow::factory()->create(['workspace_id' => $config->workspace_id]);
    $workflow->replaceGraph([], []);
    $workflow->publishVersion();
    $workflow->update(['status' => 'published']);

    Http::fake([
        githubFakeUrl($config, $workflow) => Http::response(['sha' => 'existing-sha'], 200),
    ]);

    (new SyncWorkflowsToGit($config->id))->handle();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT' && ($request->data()['sha'] ?? null) === 'existing-sha');
});

it('does nothing for a non-github provider', function () {
    $config = GitSyncConfig::factory()->create(['provider' => 'gitlab']);
    Workflow::factory()->create(['workspace_id' => $config->workspace_id, 'status' => 'published']);

    Http::fake();

    (new SyncWorkflowsToGit($config->id))->handle();

    Http::assertNothingSent();
});

it('skips an inactive config entirely', function () {
    $config = GitSyncConfig::factory()->create(['is_active' => false]);
    Workflow::factory()->create(['workspace_id' => $config->workspace_id, 'status' => 'published']);

    Http::fake();

    (new SyncWorkflowsToGit($config->id))->handle();

    Http::assertNothingSent();
    expect($config->fresh()->last_synced_at)->toBeNull();
});

it('keeps syncing remaining workflows when one request fails', function () {
    $config = GitSyncConfig::factory()->create(['repository' => 'acme/workflows', 'base_path' => 'workflows']);
    $failing = Workflow::factory()->create(['workspace_id' => $config->workspace_id]);
    $failing->replaceGraph([], []);
    $failing->publishVersion();
    $failing->update(['status' => 'published']);

    $succeeding = Workflow::factory()->create(['workspace_id' => $config->workspace_id]);
    $succeeding->replaceGraph([], []);
    $succeeding->publishVersion();
    $succeeding->update(['status' => 'published']);

    Http::fake([
        githubFakeUrl($config, $failing) => Http::response([], 500),
        githubFakeUrl($config, $succeeding) => Http::response(['sha' => null], 404),
    ]);

    (new SyncWorkflowsToGit($config->id))->handle();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), "{$failing->slug}.json"));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), "{$succeeding->slug}.json") && $request->method() === 'PUT');
    expect($config->fresh()->last_synced_at)->not->toBeNull();
});
