<?php

namespace App\Listeners;

use App\Events\RunUpdated;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ResumeParentWorkflowRun implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(public WorkflowRunner $runner) {}

    public function handle(RunUpdated $event): void
    {
        $run = $event->run;

        if ($run->parent_run_id === null || ! $run->status->isTerminal()) {
            return;
        }

        $this->runner->resolveSubWorkflow($run);
    }
}
