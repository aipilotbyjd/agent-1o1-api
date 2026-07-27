<?php

namespace App\Jobs\Workflows;

use App\Models\Runs\Run;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteWorkflowStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $runId,
        public string $stepKey,
    ) {}

    public function handle(WorkflowRunner $runner): void
    {
        $run = Run::find($this->runId);

        if ($run === null) {
            return;
        }

        $runner->executeStep($run, $this->stepKey);
    }
}
