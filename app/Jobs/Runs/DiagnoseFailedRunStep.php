<?php

namespace App\Jobs\Runs;

use App\Ai\Agents\StepDiagnosisAgent;
use App\Models\Runs\Run;
use App\Models\Runs\RunFixSuggestion;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\AiGenerationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ask the AI to diagnose why a run step failed and persist actionable fix
 * suggestions (config patches) the user can apply to the workflow draft.
 */
class DiagnoseFailedRunStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $runId,
        public string $stepKey,
        public ?int $requestedBy = null,
    ) {}

    public function handle(): void
    {
        $run = Run::query()->with('steps')->find($this->runId);

        if ($run === null) {
            return;
        }

        $step = $run->steps->firstWhere('key', $this->stepKey);

        if ($step === null) {
            return;
        }

        $config = [];

        if ($run->runnable instanceof Workflow) {
            $config = $run->runnable->steps()->where('key', $this->stepKey)->first()?->config ?? [];
        }

        $prompt = json_encode([
            'step_key' => $step->key,
            'step_type' => $step->type,
            'config' => $config,
            'input' => $step->input,
            'error' => $step->error ?? 'Unknown error',
        ], JSON_PRETTY_PRINT);

        try {
            $response = (new StepDiagnosisAgent)->prompt($prompt);
        } catch (Throwable) {
            return;
        }

        $parsed = json_decode((string) Str::of($response->text)->after('{')->prepend('{')->beforeLast('}')->append('}'), true) ?? [];

        RunFixSuggestion::create([
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'step_key' => $step->key,
            'step_type' => $step->type,
            'diagnosis' => (string) ($parsed['diagnosis'] ?? 'The AI could not produce a diagnosis.'),
            'suggestions' => array_values(array_filter(
                (array) ($parsed['suggestions'] ?? []),
                fn ($suggestion): bool => is_array($suggestion),
            )),
        ]);

        $usage = $response->usage->toArray();

        AiGenerationLog::create([
            'workspace_id' => $run->workspace_id,
            'created_by' => $this->requestedBy,
            'type' => 'autofix',
            'prompt_summary' => Str::limit("Diagnose failed step [{$step->key}] of run #{$run->id}", 255),
            'tokens_used' => (int) ($usage['prompt_tokens'] ?? 0) + (int) ($usage['completion_tokens'] ?? 0),
        ]);
    }
}
