<?php

namespace App\Jobs\Workflows;

use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryWorkflowStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $runId,
        public int $runStepId,
    ) {}

    public function handle(WorkflowRunner $runner): void
    {
        $run = Run::find($this->runId);
        $runStep = RunStep::find($this->runStepId);

        if ($run === null || $runStep === null) {
            return;
        }

        $graph = $run->workflowVersion?->graph ?? ['steps' => []];
        $step = collect($graph['steps'] ?? [])->firstWhere('key', $runStep->key);

        if ($step === null) {
            $run->markFailed("Step [{$runStep->key}] is missing from the published version.");

            return;
        }

        $runner->retryStep($run, $runStep, $step);
    }
}
