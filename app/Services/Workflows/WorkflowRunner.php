<?php

namespace App\Services\Workflows;

use App\Enums\RunStepStatus;
use App\Enums\WorkflowStepType;
use App\Jobs\ExecuteWorkflowStep;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\User;
use App\Models\Workflow;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\NotificationDispatcher;
use App\Services\Workflows\Handlers\AgentStepHandler;
use App\Services\Workflows\Handlers\ConditionStepHandler;
use App\Services\Workflows\Handlers\DelayStepHandler;
use App\Services\Workflows\Handlers\StepHandler;
use App\Services\Workflows\Handlers\ToolStepHandler;
use App\Services\Workflows\Handlers\TransformStepHandler;
use LogicException;
use Throwable;

class WorkflowRunner
{
    /**
     * Start a run against the workflow's current published version.
     *
     * @param  array<string, mixed>  $input
     */
    public function start(Workflow $workflow, ?User $user, array $input, string $triggerType = 'manual'): Run
    {
        $version = $workflow->currentVersion;

        $run = Run::create([
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_version_id' => $version?->id,
            'trigger_type' => $triggerType,
            'input' => $input,
            'triggered_by' => $user?->id,
        ]);

        $run->markRunning();

        if ($version === null) {
            $run->markFailed('Workflow has no published version.');

            return $run;
        }

        $entrySteps = $this->entrySteps($version->graph);

        if ($entrySteps === []) {
            $run->markFailed('Workflow has no steps.');

            return $run;
        }

        foreach ($entrySteps as $step) {
            ExecuteWorkflowStep::dispatch($run->id, $step['key']);
        }

        return $run;
    }

    /**
     * Execute one snapshot step within a run, then advance along its edges.
     */
    public function executeStep(Run $run, string $stepKey): void
    {
        $run->refresh();

        if ($run->status->isTerminal()) {
            return;
        }

        // Guard against double execution when two branches merge into one step.
        if ($run->steps()->where('key', $stepKey)->exists()) {
            return;
        }

        $step = $this->findStep($this->graphFor($run), $stepKey);

        if ($step === null) {
            $run->markFailed("Step [{$stepKey}] is missing from the published version.");

            return;
        }

        $runStep = $run->steps()->create([
            'key' => $step['key'],
            'type' => $step['type'],
            'input' => $step['config'] ?? [],
        ]);

        if ($step['type'] === WorkflowStepType::HumanApproval->value) {
            $this->requestApproval($run, $runStep, $step);

            return;
        }

        $runStep->markRunning();

        try {
            $result = $this->handlerFor($step)->handle($run, $step, $this->contextFor($run));
        } catch (Throwable $exception) {
            $runStep->markFailed($exception->getMessage());
            $run->markFailed("Step [{$step['key']}] failed: ".$exception->getMessage());

            return;
        }

        $runStep->markCompleted($result['output'], $result['usage'] ?? null);

        $this->advance($run, $step, $result['output']);
    }

    /**
     * Resolve a paused approval step: resume the run on approval, fail it on rejection.
     */
    public function resolveApproval(Run $run, RunStep $runStep, bool $approved, User $decidedBy): void
    {
        if (! $approved) {
            $runStep->markFailed("Rejected by {$decidedBy->name}.");
            $run->markFailed("Step [{$runStep->key}] was rejected.");

            return;
        }

        $runStep->markCompleted(['result' => 'true', 'approved_by' => $decidedBy->id]);
        $run->markRunning();

        $step = $this->findStep($this->graphFor($run), $runStep->key);

        if ($step === null) {
            $run->markFailed("Step [{$runStep->key}] is missing from the published version.");

            return;
        }

        $this->advance($run, $step, ['result' => 'true']);
    }

    /**
     * Pause the run and notify workspace owners/admins that a decision is needed.
     *
     * @param  array<string, mixed>  $step
     */
    private function requestApproval(Run $run, RunStep $runStep, array $step): void
    {
        $message = app(TemplateResolver::class)->resolve(
            $step['config']['message'] ?? 'A workflow step is waiting for approval.',
            $this->contextFor($run),
        );

        $runStep->markAwaitingApproval();
        $run->markAwaitingApproval();

        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->dispatch(
            $dispatcher->ownersAndAdmins($run->workspace),
            new RunApprovalRequestedNotification($run->workspace, $run, $runStep, $message),
        );
    }

    /**
     * Dispatch the next steps whose edge conditions match, or finish the run.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $output
     */
    private function advance(Run $run, array $step, array $output): void
    {
        $graph = $this->graphFor($run);
        $delaySeconds = $step['type'] === WorkflowStepType::Delay->value ? (int) ($output['seconds'] ?? 0) : 0;

        $edges = array_filter(
            $graph['edges'] ?? [],
            fn (array $edge): bool => $edge['from'] === $step['key']
                && (($edge['condition'] ?? null) === null || $edge['condition'] === ($output['result'] ?? null)),
        );

        $dispatched = false;

        foreach ($edges as $edge) {
            if ($run->steps()->where('key', $edge['to'])->exists()) {
                continue;
            }

            ExecuteWorkflowStep::dispatch($run->id, $edge['to'])
                ->delay($delaySeconds > 0 ? now()->addSeconds($delaySeconds) : null);

            $dispatched = true;
        }

        if (! $dispatched) {
            $this->finishIfDone($run, $output);
        }
    }

    /**
     * Complete the run when no steps remain in flight.
     *
     * @param  array<string, mixed>  $lastOutput
     */
    private function finishIfDone(Run $run, array $lastOutput): void
    {
        $run->refresh();

        if ($run->status->isTerminal()) {
            return;
        }

        $inFlight = $run->steps()
            ->whereIn('status', [RunStepStatus::Pending->value, RunStepStatus::Running->value, RunStepStatus::AwaitingApproval->value])
            ->exists();

        if (! $inFlight) {
            $run->markCompleted($lastOutput);
        }
    }

    /**
     * Build the template context: the trigger input plus each completed step's output.
     *
     * @return array<string, mixed>
     */
    private function contextFor(Run $run): array
    {
        $steps = $run->steps()
            ->where('status', RunStepStatus::Completed->value)
            ->get()
            ->mapWithKeys(fn (RunStep $runStep): array => [$runStep->key => $runStep->output]);

        return ['input' => $run->input ?? [], 'steps' => $steps->all()];
    }

    /**
     * @return array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}
     */
    private function graphFor(Run $run): array
    {
        return $run->workflowVersion?->graph ?? ['steps' => [], 'edges' => []];
    }

    /**
     * @param  array{steps?: array<int, array<string, mixed>>}  $graph
     * @return array<string, mixed>|null
     */
    private function findStep(array $graph, string $key): ?array
    {
        foreach ($graph['steps'] ?? [] as $step) {
            if ($step['key'] === $key) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Steps with no incoming edges — where execution begins.
     *
     * @param  array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     * @return array<int, array<string, mixed>>
     */
    private function entrySteps(array $graph): array
    {
        $targets = array_column($graph['edges'] ?? [], 'to');

        return array_values(array_filter(
            $graph['steps'] ?? [],
            fn (array $step): bool => ! in_array($step['key'], $targets, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function handlerFor(array $step): StepHandler
    {
        return match (WorkflowStepType::from($step['type'])) {
            WorkflowStepType::Agent => app(AgentStepHandler::class),
            WorkflowStepType::Tool => app(ToolStepHandler::class),
            WorkflowStepType::Condition => app(ConditionStepHandler::class),
            WorkflowStepType::Transform => app(TransformStepHandler::class),
            WorkflowStepType::Delay => app(DelayStepHandler::class),
            WorkflowStepType::HumanApproval => throw new LogicException('Approval steps are paused, not executed by a handler.'),
        };
    }
}
