<?php

namespace App\Services\Workflows;

use App\Enums\RunStepStatus;
use App\Enums\WorkflowStepType;
use App\Jobs\ExecuteWorkflowStep;
use App\Jobs\RetryWorkflowStep;
use App\Models\Run;
use App\Models\RunLog;
use App\Models\RunReplayPack;
use App\Models\RunStep;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkspaceEnvironment;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\NotificationDispatcher;
use App\Services\Workflows\Handlers\AgentStepHandler;
use App\Services\Workflows\Handlers\ConditionStepHandler;
use App\Services\Workflows\Handlers\DelayStepHandler;
use App\Services\Workflows\Handlers\LoopStepHandler;
use App\Services\Workflows\Handlers\MergeStepHandler;
use App\Services\Workflows\Handlers\StepHandler;
use App\Services\Workflows\Handlers\ToolStepHandler;
use App\Services\Workflows\Handlers\TransformStepHandler;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;
use Throwable;

class WorkflowRunner
{
    /**
     * Start a run against the workflow's current published version.
     *
     * @param  array<string, mixed>  $input
     */
    public function start(
        Workflow $workflow,
        ?User $user,
        array $input,
        string $triggerType = 'manual',
        ?WorkspaceEnvironment $environment = null,
        ?WorkflowVersion $version = null,
    ): Run {
        $version ??= $workflow->currentVersion;

        $run = Run::create([
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_version_id' => $version?->id,
            'environment_id' => $environment?->id,
            'trigger_type' => $triggerType,
            'input' => $input,
            'triggered_by' => $user?->id,
        ]);

        $run->markRunning();
        $this->log($run, 'info', 'Run started.');

        if ($version === null) {
            $this->log($run, 'error', 'Workflow has no published version.');
            $run->markFailed('Workflow has no published version.');

            return $run;
        }

        $entrySteps = $this->entrySteps($version->graph);

        if ($entrySteps === []) {
            $this->log($run, 'error', 'Workflow has no steps.');
            $run->markFailed('Workflow has no steps.');

            return $run;
        }

        foreach ($entrySteps as $step) {
            ExecuteWorkflowStep::dispatch($run->id, $step['key']);
        }

        return $run;
    }

    /**
     * Start a run against a replay pack's captured graph rather than the workflow's current
     * published version — materialized as a new WorkflowVersion (tagged in its notes) so the
     * run's workflow_version_id stays a real, queryable version, same as any other run.
     */
    public function replay(RunReplayPack $pack, ?User $user): Run
    {
        $workflow = $pack->workflow;

        $version = $workflow->versions()->create([
            'version' => ((int) $workflow->versions()->max('version')) + 1,
            'graph' => $pack->version_snapshot,
            'notes' => "Replay of pack #{$pack->id}".($pack->label ? " ({$pack->label})" : ''),
            'published_by' => $user?->id,
        ]);

        return $this->start($workflow, $user, $pack->trigger_data ?? [], 'replay', version: $version);
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

        // Guard against double execution when two branches dispatch the same step.
        if ($run->steps()->where('key', $stepKey)->exists()) {
            return;
        }

        $graph = $this->graphFor($run);
        $step = $this->findStep($graph, $stepKey);

        if ($step === null) {
            $this->log($run, 'error', "Step [{$stepKey}] is missing from the published version.", $stepKey);
            $run->markFailed("Step [{$stepKey}] is missing from the published version.");

            return;
        }

        $stepType = WorkflowStepType::from($step['type']);
        $branches = $this->predecessorKeys($graph, $stepKey);

        // A merge step only runs once every incoming branch has completed; whichever
        // branch arrives first (or every branch but the last) simply defers and does
        // nothing — the last branch to complete is the one that proceeds.
        if ($stepType === WorkflowStepType::Merge) {
            if (! $this->allComplete($run, $branches)) {
                return;
            }

            $step['config'] = [...($step['config'] ?? []), 'branches' => $branches];
        }

        try {
            $runStep = $run->steps()->create([
                'key' => $step['key'],
                'type' => $step['type'],
                'input' => $step['config'] ?? [],
                'max_attempts' => max(1, (int) ($step['config']['max_attempts'] ?? 1)),
                'retry_delay_seconds' => (int) ($step['config']['retry_delay_seconds'] ?? 0),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another branch already created this step concurrently.
            return;
        }

        if ($stepType === WorkflowStepType::HumanApproval) {
            $this->requestApproval($run, $runStep, $step);

            return;
        }

        if ($stepType === WorkflowStepType::SubWorkflow) {
            $this->startSubWorkflow($run, $runStep, $step);

            return;
        }

        $this->log($run, 'info', "Step [{$step['key']}] started.", $step['key']);

        $this->runHandlerAndAdvance($run, $runStep, $step);
    }

    /**
     * Run a step's handler, marking it completed/failed and advancing the graph. Shared by
     * the initial attempt and by retries of an already-created run step.
     *
     * @param  array<string, mixed>  $step
     */
    private function runHandlerAndAdvance(Run $run, RunStep $runStep, array $step): void
    {
        $runStep->markRunning();

        try {
            $result = $this->handlerFor($step)->handle($run, $step, $this->contextFor($run));
        } catch (Throwable $exception) {
            $this->handleStepFailure($run, $runStep, $step, $exception->getMessage());

            return;
        }

        $runStep->markCompleted($result['output'], $result['usage'] ?? null);
        $this->log($run, 'info', "Step [{$step['key']}] completed.", $step['key']);

        $this->advance($run, $step, $result['output']);
    }

    /**
     * Retry an already-created run step after a scheduled backoff.
     *
     * @param  array<string, mixed>  $step
     */
    public function retryStep(Run $run, RunStep $runStep, array $step): void
    {
        $run->refresh();
        $runStep->refresh();

        if ($run->status->isTerminal() || $runStep->status->isTerminal()) {
            return;
        }

        $this->runHandlerAndAdvance($run, $runStep, $step);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function handleStepFailure(Run $run, RunStep $runStep, array $step, string $message): void
    {
        if ($runStep->canRetry()) {
            $delay = $runStep->retry_delay_seconds;
            $this->log($run, 'warning', "Step [{$step['key']}] failed, retrying: ".$message, $step['key']);
            $runStep->scheduleRetry($message);

            RetryWorkflowStep::dispatch($run->id, $runStep->id)
                ->delay($delay > 0 ? now()->addSeconds($delay) : null);

            return;
        }

        $this->log($run, 'error', "Step [{$step['key']}] failed: ".$message, $step['key']);
        $runStep->markFailed($message);
        $run->markFailed("Step [{$step['key']}] failed: ".$message);
    }

    /**
     * Resolve a paused approval step: resume the run on approval, fail it on rejection.
     */
    public function resolveApproval(Run $run, RunStep $runStep, bool $approved, User $decidedBy): void
    {
        if (! $approved) {
            $this->log($run, 'warning', "Step [{$runStep->key}] rejected by {$decidedBy->name}.", $runStep->key);
            $runStep->markFailed("Rejected by {$decidedBy->name}.");
            $run->markFailed("Step [{$runStep->key}] was rejected.");

            return;
        }

        $this->log($run, 'info', "Step [{$runStep->key}] approved by {$decidedBy->name}.", $runStep->key);
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

        $this->log($run, 'info', "Step [{$step['key']}] is awaiting approval.", $step['key']);
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
            $this->log($run, 'info', 'Run completed.');
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

        // Every workspace variable (secret or not) is available to step templates as
        // {{ variables.key }} — a step that maps one into its output is a deliberate
        // choice by the workflow author, same as referencing any other context value.
        $variables = Variable::query()
            ->where('workspace_id', $run->workspace_id)
            ->get()
            ->mapWithKeys(fn (Variable $variable): array => [$variable->key => $variable->value]);

        // The run's environment (if any) overrides matching workspace variable keys —
        // e.g. a "staging" release sees a different API base URL than "production".
        if ($run->environment_id !== null) {
            $variables = $variables->merge($run->environment?->variables ?? []);
        }

        return ['input' => $run->input ?? [], 'steps' => $steps->all(), 'variables' => $variables->all()];
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
     * The distinct step keys with an edge pointing at the given step.
     *
     * @param  array{edges?: array<int, array<string, mixed>>}  $graph
     * @return array<int, string>
     */
    private function predecessorKeys(array $graph, string $stepKey): array
    {
        return array_values(array_unique(array_column(
            array_filter($graph['edges'] ?? [], fn (array $edge): bool => $edge['to'] === $stepKey),
            'from',
        )));
    }

    /**
     * @param  array<int, string>  $stepKeys
     */
    private function allComplete(Run $run, array $stepKeys): bool
    {
        if ($stepKeys === []) {
            return true;
        }

        $completed = $run->steps()
            ->where('status', RunStepStatus::Completed->value)
            ->whereIn('key', $stepKeys)
            ->pluck('key');

        return count(array_intersect($stepKeys, $completed->all())) === count($stepKeys);
    }

    /**
     * Start a child run for a sub-workflow step and pause the parent step until it finishes
     * (see the RunUpdated listener that resumes the parent when the child completes/fails).
     *
     * @param  array<string, mixed>  $step
     */
    private function startSubWorkflow(Run $run, RunStep $runStep, array $step): void
    {
        $config = $step['config'] ?? [];
        $workflow = Workflow::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['workflow_id'] ?? null);

        if ($workflow === null) {
            $this->handleStepFailure($run, $runStep, $step, "Step [{$step['key']}] references a missing workflow.");

            return;
        }

        $runStep->markRunning();

        $input = app(TemplateResolver::class)->resolveArray($config['input'] ?? [], $this->contextFor($run));

        $child = $this->start($workflow, $run->triggeredBy, $input, 'sub_workflow', $run->environment);
        $child->update(['parent_run_id' => $run->id, 'parent_step_id' => $runStep->id]);

        // The child may already have finished synchronously (e.g. the sync queue driver),
        // in which case the in-memory $child instance is stale — reload it from the row
        // that ExecuteWorkflowStep's own fresh Run::find() instance actually updated.
        $child->refresh();

        if ($child->status->isTerminal()) {
            $this->resolveSubWorkflow($child);
        }
    }

    /**
     * Resume the parent run's step once its child sub-workflow run finishes.
     */
    public function resolveSubWorkflow(Run $child): void
    {
        if ($child->parent_run_id === null || $child->parent_step_id === null) {
            return;
        }

        $parentRun = $child->parentRun;
        $parentStep = $child->parentStep;

        if ($parentRun === null || $parentStep === null || $parentStep->status->isTerminal()) {
            return;
        }

        if ($child->status->value !== 'completed') {
            $this->handleStepFailure(
                $parentRun,
                $parentStep,
                ['key' => $parentStep->key, 'config' => []],
                "Sub-workflow run [{$child->id}] failed: ".($child->error ?? 'unknown error'),
            );

            return;
        }

        $parentRun->markRunning();
        $parentStep->markCompleted(['run_id' => $child->id, 'output' => $child->output]);

        $step = $this->findStep($this->graphFor($parentRun), $parentStep->key);

        if ($step === null) {
            $parentRun->markFailed("Step [{$parentStep->key}] is missing from the published version.");

            return;
        }

        $this->advance($parentRun, $step, ['run_id' => $child->id, 'output' => $child->output]);
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
            WorkflowStepType::Merge => app(MergeStepHandler::class),
            WorkflowStepType::Loop => app(LoopStepHandler::class),
            WorkflowStepType::HumanApproval => throw new LogicException('Approval steps are paused, not executed by a handler.'),
            WorkflowStepType::SubWorkflow => throw new LogicException('Sub-workflow steps are paused, not executed by a handler.'),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(Run $run, string $level, string $message, ?string $stepKey = null, array $context = []): void
    {
        RunLog::create([
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'step_key' => $stepKey,
            'level' => $level,
            'message' => $message,
            'context' => $context === [] ? null : $context,
            'logged_at' => now(),
        ]);
    }
}
