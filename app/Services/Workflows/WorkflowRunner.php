<?php

namespace App\Services\Workflows;

use App\Enums\Runs\RunStepStatus;
use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Models\Runs\RunReplayPack;
use App\Models\Runs\RunStep;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\WorkspaceEnvironment;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workflows\Engine\GraphAdvancer;
use App\Services\Workflows\Engine\LoopCoordinator;
use App\Services\Workflows\Engine\RunLogger;
use App\Services\Workflows\Engine\RunStarter;
use App\Services\Workflows\Engine\StepContextBuilder;
use App\Services\Workflows\Engine\StepFailureHandler;
use App\Services\Workflows\Engine\SubWorkflowCoordinator;
use App\Services\Workflows\Handlers\AgentStepHandler;
use App\Services\Workflows\Handlers\ConditionStepHandler;
use App\Services\Workflows\Handlers\DelayStepHandler;
use App\Services\Workflows\Handlers\LoopStepHandler;
use App\Services\Workflows\Handlers\MergeStepHandler;
use App\Services\Workflows\Handlers\StepHandler;
use App\Services\Workflows\Handlers\ToolStepHandler;
use App\Services\Workflows\Handlers\TransformStepHandler;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * The engine's entry point: executes one step of a run and hands off to whichever
 * collaborator owns what happens next.
 *
 * Anything that is not "run this step" lives elsewhere — graph traversal in
 * {@see GraphAdvancer}, failure and retry policy in {@see StepFailureHandler}, child
 * runs in {@see LoopCoordinator} and {@see SubWorkflowCoordinator}. This class stays the
 * public face of all of it so callers (jobs, controllers, listeners) have one dependency.
 */
class WorkflowRunner
{
    /**
     * How long a wait step parks for when its config does not say — 24 hours.
     */
    private const DEFAULT_WAIT_TIMEOUT_MINUTES = 1440;

    public function __construct(
        private readonly RunStarter $starter,
        private readonly GraphAdvancer $advancer,
        private readonly StepFailureHandler $failures,
        private readonly StepContextBuilder $contexts,
        private readonly LoopCoordinator $loops,
        private readonly SubWorkflowCoordinator $subWorkflows,
        private readonly TemplateResolver $templates,
        private readonly NotificationDispatcher $notifications,
        private readonly RunLogger $logger,
        private readonly Container $container,
    ) {}

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
        return $this->starter->start($workflow, $user, $input, $triggerType, $environment, $version);
    }

    /**
     * Start a run against a replay pack's captured graph.
     */
    public function replay(RunReplayPack $pack, ?User $user): Run
    {
        return $this->starter->replay($pack, $user);
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

        $graph = $this->advancer->graphFor($run);
        $step = $graph->step($stepKey);

        if ($step === null) {
            $this->logger->error($run, "Step [{$stepKey}] is missing from the published version.", $stepKey);
            $run->markFailed("Step [{$stepKey}] is missing from the published version.");

            return;
        }

        $stepType = WorkflowStepType::from($step['type']);
        $options = StepOptions::fromStep($step);
        $branches = $graph->predecessorKeys($stepKey);

        // A merge step only runs once every incoming branch has completed; whichever
        // branch arrives first (or every branch but the last) simply defers and does
        // nothing — the last branch to complete is the one that proceeds.
        if ($stepType === WorkflowStepType::Merge) {
            if (! $this->advancer->allComplete($run, $branches)) {
                return;
            }

            $step['config'] = [...($step['config'] ?? []), 'branches' => $branches];
        }

        try {
            $runStep = $run->steps()->create([
                'key' => $step['key'],
                'type' => $step['type'],
                'input' => $step['config'] ?? [],
                'max_attempts' => $options->maxAttempts,
                'retry_delay_seconds' => $options->retryDelaySeconds,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another branch already created this step concurrently.
            return;
        }

        // These step types pause the run rather than returning from a handler.
        switch (true) {
            case $stepType === WorkflowStepType::HumanApproval:
                $this->requestApproval($run, $runStep, $step);

                return;

            case $stepType === WorkflowStepType::SubWorkflow:
                $this->subWorkflows->start($run, $runStep, $step);

                return;

            case $stepType === WorkflowStepType::Wait:
                $this->startWait($run, $runStep, $step);

                return;

                // A "foreach" loop fans out a child run per item and waits; "map" stays
                // a plain handler.
            case $stepType === WorkflowStepType::Loop && ($step['config']['mode'] ?? 'map') === 'foreach':
                $this->logger->info($run, "Step [{$step['key']}] started.", $step['key']);
                $this->loops->start($run, $runStep, $step);

                return;
        }

        $this->logger->info($run, "Step [{$step['key']}] started.", $step['key']);

        $this->runHandlerAndAdvance($run, $runStep, $step);
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
     * Resolve a paused approval step: resume the run on approval, fail it on rejection.
     */
    public function resolveApproval(Run $run, RunStep $runStep, bool $approved, User $decidedBy): void
    {
        if (! $approved) {
            $this->logger->warning($run, "Step [{$runStep->key}] rejected by {$decidedBy->name}.", $runStep->key);
            $runStep->markFailed("Rejected by {$decidedBy->name}.");
            $run->markFailed("Step [{$runStep->key}] was rejected.");

            return;
        }

        $this->logger->info($run, "Step [{$runStep->key}] approved by {$decidedBy->name}.", $runStep->key);
        $runStep->markCompleted(['result' => 'true', 'approved_by' => $decidedBy->id]);
        $run->markRunning();

        $this->advanceFrom($run, $runStep, ['result' => 'true']);
    }

    /**
     * Resume a waiting step because its callback URL was hit.
     *
     * @param  array<string, mixed>  $data  The callback's payload, exposed as the step's output.
     */
    public function resolveCallback(Run $run, RunStep $runStep, array $data): void
    {
        // The token is single-use: clearing it here means a replayed callback cannot
        // advance the graph a second time.
        $this->clearCallback($runStep);

        $output = ['data' => $data, 'timed_out' => false];

        $this->logger->info($run, "Step [{$runStep->key}] received its callback.", $runStep->key);
        $runStep->markCompleted($output);
        $run->markRunning();

        $this->advanceFrom($run, $runStep, $output);
    }

    /**
     * Settle a wait step whose deadline passed without a callback.
     *
     * By default an unanswered wait fails the step, so a caller that never comes back is
     * visible rather than silently swallowed. `continue_on_timeout` opts into the other
     * reading — the wait was best-effort — and carries on with `timed_out: true`.
     */
    public function expireWait(RunStep $runStep): void
    {
        $run = $runStep->run;

        if ($run === null || $runStep->status !== RunStepStatus::AwaitingCallback) {
            return;
        }

        $this->clearCallback($runStep);

        $step = $this->advancer->graphFor($run)->step($runStep->key)
            ?? ['key' => $runStep->key, 'type' => WorkflowStepType::Wait->value, 'config' => []];

        if (! (bool) ($step['config']['continue_on_timeout'] ?? false)) {
            $run->markRunning();
            $this->failures->handle($run, $runStep, $step, 'Timed out waiting for a callback.');

            return;
        }

        $output = ['data' => [], 'timed_out' => true];

        $this->logger->warning($run, "Step [{$runStep->key}] timed out waiting for a callback.", $runStep->key);
        $runStep->markCompleted($output);
        $run->markRunning();

        $this->advancer->advance($run, $step, $output);
    }

    /**
     * Resume the parent run's step once its child sub-workflow run finishes.
     */
    public function resolveSubWorkflow(Run $child): void
    {
        $this->subWorkflows->resume($child);
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

        $options = StepOptions::fromStep($step);
        $startedAt = microtime(true);

        try {
            $result = $this->handlerFor($step)->handle($run, $step, $this->contexts->build($run, $step));
        } catch (Throwable $exception) {
            $this->failures->handle($run, $runStep, $step, $exception->getMessage());

            return;
        }

        $elapsed = microtime(true) - $startedAt;

        // A step that overran its budget is failed rather than accepted late. This does
        // not interrupt work already in flight — hard cancellation belongs to the queue
        // worker's own timeout — but it stops an overrunning step advancing the graph.
        if ($options->hasTimeout() && $elapsed > $options->timeoutSeconds) {
            $this->failures->handle($run, $runStep, $step, sprintf(
                'Step exceeded its timeout of %ds (took %.1fs).',
                $options->timeoutSeconds,
                $elapsed,
            ));

            return;
        }

        $runStep->markCompleted($result['output'], $result['usage'] ?? null);
        $this->logger->info($run, "Step [{$step['key']}] completed.", $step['key']);

        $this->advancer->advance($run, $step, $result['output']);
    }

    /**
     * Pause the run and notify workspace owners/admins that a decision is needed.
     *
     * @param  array<string, mixed>  $step
     */
    private function requestApproval(Run $run, RunStep $runStep, array $step): void
    {
        $message = $this->templates->resolve(
            $step['config']['message'] ?? 'A workflow step is waiting for approval.',
            $this->contexts->build($run, $step),
        );

        $this->logger->info($run, "Step [{$step['key']}] is awaiting approval.", $step['key']);
        $runStep->markAwaitingApproval();
        $run->markAwaitingApproval();

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($run->workspace),
            new RunApprovalRequestedNotification($run->workspace, $run, $runStep, $message),
        );
    }

    /**
     * Park the run on a wait step, handing out a one-time callback URL.
     *
     * @param  array<string, mixed>  $step
     */
    private function startWait(Run $run, RunStep $runStep, array $step): void
    {
        $timeoutMinutes = max(1, (int) ($step['config']['timeout_minutes'] ?? self::DEFAULT_WAIT_TIMEOUT_MINUTES));

        $runStep->markAwaitingCallback(Str::random(48), now()->addMinutes($timeoutMinutes));
        $run->markAwaitingCallback();

        $this->logger->info($run, "Step [{$step['key']}] is waiting for a callback.", $step['key']);
    }

    private function clearCallback(RunStep $runStep): void
    {
        $runStep->forceFill(['callback_token' => null, 'callback_expires_at' => null])->save();
    }

    /**
     * Advance from a step that was resumed rather than executed, failing the run if the
     * published version no longer describes it.
     *
     * @param  array<string, mixed>  $output
     */
    private function advanceFrom(Run $run, RunStep $runStep, array $output): void
    {
        $step = $this->advancer->graphFor($run)->step($runStep->key);

        if ($step === null) {
            $run->markFailed("Step [{$runStep->key}] is missing from the published version.");

            return;
        }

        $this->advancer->advance($run, $step, $output);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function handlerFor(array $step): StepHandler
    {
        return $this->container->make(match (WorkflowStepType::from($step['type'])) {
            WorkflowStepType::Agent => AgentStepHandler::class,
            WorkflowStepType::Tool => ToolStepHandler::class,
            WorkflowStepType::Condition => ConditionStepHandler::class,
            WorkflowStepType::Transform => TransformStepHandler::class,
            WorkflowStepType::Delay => DelayStepHandler::class,
            WorkflowStepType::Merge => MergeStepHandler::class,
            WorkflowStepType::Loop => LoopStepHandler::class,
            WorkflowStepType::HumanApproval => throw new LogicException('Approval steps are paused, not executed by a handler.'),
            WorkflowStepType::SubWorkflow => throw new LogicException('Sub-workflow steps are paused, not executed by a handler.'),
            WorkflowStepType::Wait => throw new LogicException('Wait steps are paused, not executed by a handler.'),
            WorkflowStepType::Flow => throw new LogicException('Flow nodes are canvas markers, not executable steps.'),
        });
    }
}
