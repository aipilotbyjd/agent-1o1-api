<?php

namespace App\Services\Workflows\Engine;

use App\Enums\Runs\RunStatus;
use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\TemplateResolver;

/**
 * Runs a sub-workflow step as a child run, pausing the parent step until it settles.
 *
 * Also owns the resume side for *every* kind of child run, loop iterations included —
 * the RunUpdated listener has only the finished child to go on, so working out which
 * parent it belongs to and how that parent joins lives here.
 */
class SubWorkflowCoordinator
{
    public function __construct(
        private readonly RunStarter $starter,
        private readonly GraphAdvancer $advancer,
        private readonly StepFailureHandler $failures,
        private readonly StepContextBuilder $contexts,
        private readonly LoopCoordinator $loops,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * @param  array<string, mixed>  $step
     */
    public function start(Run $run, RunStep $runStep, array $step): void
    {
        $config = $step['config'] ?? [];
        $workflow = Workflow::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['workflow_id'] ?? null);

        if ($workflow === null) {
            $this->failures->handle($run, $runStep, $step, "Step [{$step['key']}] references a missing workflow.");

            return;
        }

        $runStep->markRunning();

        $input = $this->templates->resolveArray($config['input'] ?? [], $this->contexts->build($run, $step));

        $child = $this->starter->start($workflow, $run->triggeredBy, $input, 'sub_workflow', $run->environment);
        $child->update(['parent_run_id' => $run->id, 'parent_step_id' => $runStep->id]);

        // The child may already have finished synchronously (e.g. the sync queue driver),
        // in which case the in-memory $child instance is stale — reload it from the row
        // that ExecuteWorkflowStep's own fresh Run::find() instance actually updated.
        $child->refresh();

        if ($child->status->isTerminal()) {
            $this->resume($child);
        }
    }

    /**
     * Resume the parent run's step once its child run finishes.
     */
    public function resume(Run $child): void
    {
        if ($child->parent_run_id === null || $child->parent_step_id === null) {
            return;
        }

        $parentRun = $child->parentRun;
        $parentStep = $child->parentStep;

        if ($parentRun === null || $parentStep === null || $parentStep->status->isTerminal()) {
            return;
        }

        // A loop step owns many children, so it joins on all of them rather than
        // resuming on the first one to finish.
        if ($parentStep->type === WorkflowStepType::Loop->value) {
            $this->loops->join($parentRun, $parentStep);

            return;
        }

        if ($child->status !== RunStatus::Completed) {
            $this->failures->handle(
                $parentRun,
                $parentStep,
                ['key' => $parentStep->key, 'config' => []],
                "Sub-workflow run [{$child->id}] failed: ".($child->error ?? 'unknown error'),
            );

            return;
        }

        $parentRun->markRunning();
        $parentStep->markCompleted(['run_id' => $child->id, 'output' => $child->output]);

        $step = $this->advancer->graphFor($parentRun)->step($parentStep->key);

        if ($step === null) {
            $parentRun->markFailed("Step [{$parentStep->key}] is missing from the published version.");

            return;
        }

        $this->advancer->advance($parentRun, $step, ['run_id' => $child->id, 'output' => $child->output]);
    }
}
