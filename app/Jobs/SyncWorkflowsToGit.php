<?php

namespace App\Jobs;

use App\Models\GitSyncConfig;
use App\Models\Workflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncWorkflowsToGit implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $gitSyncConfigId) {}

    public function handle(): void
    {
        $config = GitSyncConfig::find($this->gitSyncConfigId);

        if ($config === null || ! $config->is_active) {
            return;
        }

        if ($config->provider !== 'github') {
            return;
        }

        $workflows = Workflow::query()
            ->where('workspace_id', $config->workspace_id)
            ->where('status', 'published')
            ->get();

        foreach ($workflows as $workflow) {
            try {
                $this->syncWorkflow($config, $workflow);
            } catch (Throwable) {
                // One workflow's failure shouldn't stop the rest of the batch.
                continue;
            }
        }

        $config->update(['last_synced_at' => now()]);
    }

    private function syncWorkflow(GitSyncConfig $config, Workflow $workflow): void
    {
        $path = trim($config->base_path, '/')."/{$workflow->slug}.json";
        $url = "https://api.github.com/repos/{$config->repository}/contents/{$path}";

        $headers = [
            'Authorization' => "Bearer {$config->access_token}",
            'Accept' => 'application/vnd.github+json',
        ];

        $existing = Http::withHeaders($headers)->get($url, ['ref' => $config->branch]);
        $sha = $existing->successful() ? $existing->json('sha') : null;

        $payload = [
            'name' => $workflow->name,
            'slug' => $workflow->slug,
            'description' => $workflow->description,
            'graph' => $workflow->currentVersion?->graph,
        ];

        $body = [
            'message' => "Sync workflow: {$workflow->name}",
            'content' => base64_encode(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)),
            'branch' => $config->branch,
        ];

        if ($sha !== null) {
            $body['sha'] = $sha;
        }

        Http::withHeaders($headers)->put($url, $body);
    }
}
