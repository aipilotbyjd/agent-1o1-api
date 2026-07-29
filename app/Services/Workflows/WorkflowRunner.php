<?php

namespace App\Services\Workflows;

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Enums\Workflows\WorkflowStepType;
use App\Jobs\Workflows\ExecuteWorkflowStep;
use App\Jobs\Workflows\RetryWorkflowStep;
use App\Models\Runs\Run;
use App\Models\Runs\RunLog;
use App\Models\Runs\RunReplayPack;
use App\Models\Runs\RunStep;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\WorkspaceEnvironment;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Runs\SecretRedactor;
use App\Services\Workflows\Handlers\AgentStepHandler;
use App\Services\Workflows\Handlers\ConditionStepHandler;
use App\Services\Workflows\Handlers\DelayStepHandler;
use App\Services\Workflows\Handlers\LoopStepHandler;
use App\Services\Workflows\Handlers\MergeStepHandler;
use App\Services\Workflows\Handlers\StepHandler;
use App\Services\Workflows\Handlers\ToolStepHandler;
use App\Services\Workflows\Handlers\TransformStepHandler;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class WorkflowRunner
{
    /**
     * The edge condition that routes a failed step somewhere other than the end of the run.
     */
    private const ERROR_CONDITION = 'error';

    private const MAX_BACKOFF_SECONDS = 3600;

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

        // A "foreach" loop fans out a child run per item and waits, so it pauses like a
        // sub-workflow rather than returning from a handler. "map" stays a plain handler.
        if ($stepType === WorkflowStepType::Loop && ($step['config']['mode'] ?? 'map') === 'foreach') {
            $this->log($run, 'info', "Step [{$step['key']}] started.", $step['key']);
            $this->startLoopFanOut($run, $runStep, $step);

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

        $timeout = (int) ($step['config']['timeout_seconds'] ?? 0);
        $startedAt = microtime(true);

        try {
            $result = $this->handlerFor($step)->handle($run, $step, $this->contextFor($run, $step));
        } catch (Throwable $exception) {
            $this->handleStepFailure($run, $runStep, $step, $exception->getMessage());

            return;
        }

        $elapsed = microtime(true) - $startedAt;

        // A step that overran its budget is failed rather than accepted late. This does
        // not interrupt work already in flight — hard cancellation belongs to the queue
        // worker's own timeout — but it stops an overrunning step advancing the graph.
        if ($timeout > 0 && $elapsed > $timeout) {
            $this->handleStepFailure($run, $runStep, $step, sprintf(
                'Step exceeded its timeout of %ds (took %.1fs).',
                $timeout,
                $elapsed,
            ));

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
        // Failure messages are built from exception text and upstream response bodies,
        // either of which can echo a credential straight back into the run record.
        $message = app(SecretRedactor::class)->redact($run->workspace_id, $message);

        if ($runStep->canRetry()) {
            $delay = $this->backoffSeconds($runStep->retry_delay_seconds, $runStep->attempt);
            $this->log($run, 'warning', "Step [{$step['key']}] failed, retrying in {$delay}s: ".$message, $step['key']);
            $runStep->scheduleRetry($message);

            RetryWorkflowStep::dispatch($run->id, $runStep->id)
                ->delay($delay > 0 ? now()->addSeconds($delay) : null);

            return;
        }

        $this->log($run, 'error', "Step [{$step['key']}] failed: ".$message, $step['key']);
        $runStep->markFailed($message);

        $hasErrorEdge = $this->edgeBetweenAny($this->graphFor($run), $step['key']);
        $continueOnError = (bool) ($step['config']['continue_on_error'] ?? false);

        // A failure with somewhere to go is a route, not the end of the run — the step
        // stays failed, but the graph carries on down its error path.
        if ($hasErrorEdge || $continueOnError) {
            $this->advance($run, $step, ['result' => self::ERROR_CONDITION, 'error' => $message], failed: true);

            return;
        }

        $run->markFailed("Step [{$step['key']}] failed: ".$message);
    }

    /**
     * Whether the step has any outgoing `error` edge to route a failure down.
     *
     * @param  array{edges?: array<int, array<string, mixed>>}  $graph
     */
    private function edgeBetweenAny(array $graph, string $stepKey): bool
    {
        foreach ($graph['edges'] ?? [] as $edge) {
            if ($edge['from'] === $stepKey && ($edge['condition'] ?? null) === self::ERROR_CONDITION) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exponential backoff with jitter. A fixed delay makes every worker retry a failing
     * dependency in lockstep, so each attempt doubles the wait and is spread by up to
     * 25% to break up the thundering herd.
     */
    private function backoffSeconds(int $base, int $attempt): int
    {
        if ($base <= 0) {
            return 0;
        }

        $delay = min($base * (2 ** max(0, $attempt - 1)), self::MAX_BACKOFF_SECONDS);

        return (int) round($delay + random_int(0, (int) max(1, $delay * 0.25)));
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
            $this->contextFor($run, $step),
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
    private function advance(Run $run, array $step, array $output, bool $failed = false): void
    {
        $graph = $this->graphFor($run);
        $delaySeconds = $step['type'] === WorkflowStepType::Delay->value ? (int) ($output['seconds'] ?? 0) : 0;

        $outgoing = array_filter(
            $graph['edges'] ?? [],
            fn (array $edge): bool => $edge['from'] === $step['key'],
        );

        $taken = array_filter(
            $outgoing,
            fn (array $edge): bool => $this->edgeMatches($edge, $output, $failed),
        );

        // Dead branches are settled *before* the live ones are dispatched. A dispatched
        // step can run all the way to a downstream merge synchronously, and that merge
        // must already be able to see which of its branches will never arrive.
        foreach ($outgoing as $edge) {
            if (! $this->edgeMatches($edge, $output, $failed)) {
                $this->skipUnreachable($run, $graph, $edge['to']);
            }
        }

        $dispatched = false;

        foreach ($taken as $edge) {
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
     * Whether an edge is followed given the source step's outcome.
     *
     * An `error` edge is followed only when the step failed, and never otherwise — an
     * unconditional edge must not fire on a failure, or a failed step would silently
     * continue down its happy path.
     *
     * @param  array<string, mixed>  $edge
     * @param  array<string, mixed>  $output
     */
    private function edgeMatches(array $edge, array $output, bool $failed): bool
    {
        $condition = $edge['condition'] ?? null;

        if ($failed) {
            return $condition === self::ERROR_CONDITION;
        }

        return $condition !== self::ERROR_CONDITION
            && ($condition === null || $condition === ($output['result'] ?? null));
    }

    /**
     * Mark a step (and anything only reachable through it) skipped, once no live
     * predecessor can still dispatch it.
     *
     * @param  array{steps?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $graph
     */
    private function skipUnreachable(Run $run, array $graph, string $stepKey): void
    {
        if ($run->steps()->where('key', $stepKey)->exists()) {
            return;
        }

        $predecessorKeys = $this->predecessorKeys($graph, $stepKey);

        foreach ($predecessorKeys as $predecessorKey) {
            $predecessor = $run->steps()->where('key', $predecessorKey)->first();

            // A predecessor that has not run, or is still running, may yet dispatch it.
            if ($predecessor === null || ! $predecessor->status->isTerminal()) {
                return;
            }

            if ($predecessor->status === RunStepStatus::Completed || $predecessor->status === RunStepStatus::Failed) {
                $edge = $this->edgeBetween($graph, $predecessorKey, $stepKey);

                if ($edge !== null && $this->edgeMatches(
                    $edge,
                    $predecessor->output ?? [],
                    $predecessor->status === RunStepStatus::Failed,
                )) {
                    // Still reachable — but this skip may have settled the last branch a
                    // waiting merge needed, so give it another chance to run.
                    if ($this->allComplete($run, $predecessorKeys)) {
                        ExecuteWorkflowStep::dispatch($run->id, $stepKey);
                    }

                    return;
                }
            }
        }

        try {
            $runStep = $run->steps()->create(['key' => $stepKey, 'type' => $this->findStep($graph, $stepKey)['type'] ?? 'transform']);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $runStep->markSkipped();
        $this->log($run, 'info', "Step [{$stepKey}] was skipped — no branch reaches it.", $stepKey);

        foreach ($graph['edges'] ?? [] as $edge) {
            if ($edge['from'] === $stepKey) {
                $this->skipUnreachable($run, $graph, $edge['to']);
            }
        }
    }

    /**
     * @param  array{edges?: array<int, array<string, mixed>>}  $graph
     * @return array<string, mixed>|null
     */
    private function edgeBetween(array $graph, string $from, string $to): ?array
    {
        foreach ($graph['edges'] ?? [] as $edge) {
            if ($edge['from'] === $from && $edge['to'] === $to) {
                return $edge;
            }
        }

        return null;
    }

    /**
     * Complete the run when no steps remain in flight.
     *
     * The check and the write happen under a row lock on the run: two branches finishing
     * at once would otherwise both observe "nothing in flight" and complete a run whose
     * sibling step is about to be created.
     *
     * @param  array<string, mixed>  $lastOutput
     */
    private function finishIfDone(Run $run, array $lastOutput): void
    {
        DB::transaction(function () use ($run, $lastOutput): void {
            $locked = Run::query()->lockForUpdate()->find($run->id);

            if ($locked === null || $locked->status->isTerminal()) {
                return;
            }

            $inFlight = $locked->steps()
                ->whereIn('status', [
                    RunStepStatus::Pending->value,
                    RunStepStatus::Running->value,
                    RunStepStatus::AwaitingApproval->value,
                ])
                ->exists();

            if ($inFlight) {
                return;
            }

            $this->log($locked, 'info', 'Run completed.');
            $locked->markCompleted($lastOutput);
        });

        $run->refresh();
    }

    /**
     * Build the template context: the trigger input plus each completed step's output.
     *
     * @return array<string, mixed>
     */
    private function contextFor(Run $run, ?array $step = null): array
    {
        $steps = $run->steps()
            ->where('status', RunStepStatus::Completed->value)
            ->get()
            ->mapWithKeys(fn (RunStep $runStep): array => [$runStep->key => $runStep->output]);

        $variables = Variable::query()
            ->where('workspace_id', $run->workspace_id)
            ->get()
            ->mapWithKeys(fn (Variable $variable): array => [$variable->key => $variable->value]);

        // The run's environment (if any) overrides matching workspace variable keys —
        // e.g. a "staging" release sees a different API base URL than "production".
        if ($run->environment_id !== null) {
            $variables = $variables->merge($run->environment?->variables ?? []);
        }

        // Only the variables a step actually names are handed to it. Every step used to
        // receive every workspace secret, so one careless `{{ variables }}` mapping — or
        // one connector that echoes its input — exposed credentials the step never used.
        if ($step !== null) {
            $referenced = $this->referencedVariableKeys($step['config'] ?? []);
            $variables = $variables->only($referenced);
        }

        return ['input' => $run->input ?? [], 'steps' => $steps->all(), 'variables' => $variables->all()];
    }

    /**
     * Every `variables.x` path mentioned anywhere in a step's config.
     *
     * @param  array<array-key, mixed>  $config
     * @return array<int, string>
     */
    private function referencedVariableKeys(array $config): array
    {
        $encoded = json_encode($config);

        if ($encoded === false) {
            return [];
        }

        preg_match_all('/variables\.([\w\-]+)/', $encoded, $matches);

        return array_values(array_unique($matches[1] ?? []));
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

        // A skipped branch counts as settled: it will never complete, and waiting on it
        // is exactly the deadlock this accounting exists to avoid.
        $settled = $run->steps()
            ->whereIn('status', [RunStepStatus::Completed->value, RunStepStatus::Skipped->value])
            ->whereIn('key', $stepKeys)
            ->pluck('key');

        return count(array_intersect($stepKeys, $settled->all())) === count($stepKeys);
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

        $input = app(TemplateResolver::class)->resolveArray($config['input'] ?? [], $this->contextFor($run, $step));

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
     * Fan a loop step out into one child run per item.
     *
     * Each iteration is a real child run of the configured workflow, so items execute on
     * the queue in parallel, fail in isolation, and are inspectable individually — rather
     * than being flattened into one synchronous pass inside a handler.
     *
     * @param  array<string, mixed>  $step
     */
    private function startLoopFanOut(Run $run, RunStep $runStep, array $step): void
    {
        $config = $step['config'] ?? [];
        $context = $this->contextFor($run, $step);
        $items = data_get($context, (string) ($config['items'] ?? ''));

        if (! is_array($items)) {
            $this->handleStepFailure($run, $runStep, $step, "Step [{$step['key']}] items path did not resolve to a list.");

            return;
        }

        $workflow = Workflow::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['workflow_id'] ?? null);

        if ($workflow === null) {
            $this->handleStepFailure($run, $runStep, $step, "Step [{$step['key']}] references a missing workflow.");

            return;
        }

        $items = array_values($items);
        $runStep->markRunning();

        // The resolved items are persisted on the step so later iterations can be started
        // from a queue worker that no longer has the context that produced them.
        $runStep->update(['input' => [...$config, '_loop_items' => $items]]);

        if ($items === []) {
            $runStep->markCompleted(['results' => [], 'count' => 0, 'failed' => 0]);
            $this->advance($run, $step, ['results' => [], 'count' => 0, 'failed' => 0]);

            return;
        }

        $concurrency = max(1, (int) ($config['max_concurrent'] ?? count($items)));

        // The whole opening batch is started before joining. On a synchronous queue a
        // child finishes inside its own dispatch, and joining there would see a partial
        // batch and release iterations this loop is already about to start.
        foreach (range(0, min($concurrency, count($items)) - 1) as $index) {
            $this->startLoopItem($run, $runStep, $workflow, $items[$index], $index, $context, join: false);
        }

        $this->resolveLoopFanOut($run, $runStep);
    }

    /**
     * Start one iteration's child run.
     *
     * @param  array<string, mixed>  $context
     */
    private function startLoopItem(Run $run, RunStep $runStep, Workflow $workflow, mixed $item, int $index, array $context, bool $join = true): void
    {
        $config = $runStep->input ?? [];

        $input = app(TemplateResolver::class)->resolveArray(
            $config['input'] ?? [],
            [...$context, 'item' => $item, 'index' => $index],
        );

        $child = $this->start(
            $workflow,
            $run->triggeredBy,
            $input === [] ? ['item' => $item, 'index' => $index] : $input,
            'loop',
            $run->environment,
        );

        $child->update([
            'parent_run_id' => $run->id,
            'parent_step_id' => $runStep->id,
            'loop_index' => $index,
        ]);

        // On the sync queue the child has already finished by now, and the in-memory
        // instance is stale — reload before deciding whether the join can proceed.
        $child->refresh();

        if ($join && $child->status->isTerminal()) {
            $this->resolveLoopFanOut($run, $runStep);
        }
    }

    /**
     * Join a loop step: start any queued iterations, then finish once all have settled.
     */
    private function resolveLoopFanOut(Run $run, RunStep $runStep): void
    {
        $runStep->refresh();

        if ($runStep->status->isTerminal()) {
            return;
        }

        $config = $runStep->input ?? [];
        $items = $config['_loop_items'] ?? [];
        $policy = $config['on_item_error'] ?? 'fail_fast';

        $children = Run::query()->where('parent_step_id', $runStep->id)->orderBy('loop_index')->get();
        $failed = $children->filter(fn (Run $child): bool => $child->status === RunStatus::Failed);

        if ($failed->isNotEmpty() && $policy === 'fail_fast') {
            $this->finishLoop($run, $runStep, $children, $items, $policy);

            return;
        }

        if ($children->contains(fn (Run $child): bool => ! $child->status->isTerminal())) {
            return;
        }

        // Every started iteration has settled; release the next queued one if any remain.
        if ($children->count() < count($items)) {
            $workflow = Workflow::query()->find($config['workflow_id'] ?? null);
            $index = $children->count();

            if ($workflow !== null) {
                $this->startLoopItem($run, $runStep, $workflow, $items[$index], $index, $this->contextFor($run));

                return;
            }
        }

        $this->finishLoop($run, $runStep, $children, $items, $policy);
    }

    /**
     * @param  Collection<int, Run>  $children
     * @param  array<int, mixed>  $items
     */
    private function finishLoop(Run $run, RunStep $runStep, $children, array $items, string $policy): void
    {
        $results = $children->map(fn (Run $child): array => [
            'index' => $child->loop_index,
            'run_id' => $child->id,
            'status' => $child->status->value,
            'output' => $child->output,
            'error' => $child->error,
        ])->values()->all();

        $failed = $children->filter(fn (Run $child): bool => $child->status !== RunStatus::Completed);

        $output = [
            'results' => $results,
            'count' => count($results),
            'failed' => $failed->count(),
            'errors' => $failed->map(fn (Run $child): array => [
                'index' => $child->loop_index,
                'error' => $child->error,
            ])->values()->all(),
        ];

        $step = $this->findStep($this->graphFor($run), $runStep->key) ?? ['key' => $runStep->key, 'config' => []];

        // fail_fast stops the run on the first bad item; collect_errors and continue both
        // carry on, differing only in whether the failures stay visible in the output.
        if ($failed->isNotEmpty() && $policy === 'fail_fast') {
            $this->handleStepFailure(
                $run,
                $runStep,
                $step,
                "Loop step [{$runStep->key}] had {$failed->count()} failed iteration(s).",
            );

            return;
        }

        if ($policy === 'continue') {
            $output['results'] = array_values(array_filter(
                $results,
                fn (array $result): bool => $result['status'] === RunStatus::Completed->value,
            ));
        }

        $run->markRunning();
        $runStep->markCompleted($output);
        $this->log($run, 'info', "Step [{$runStep->key}] completed ".count($items).' iteration(s).', $runStep->key);

        $this->advance($run, $step, $output);
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

        // A loop step owns many children, so it joins on all of them rather than
        // resuming on the first one to finish.
        if ($parentStep->type === WorkflowStepType::Loop->value) {
            $this->resolveLoopFanOut($parentRun, $parentStep);

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
        $redactor = app(SecretRedactor::class);

        RunLog::create([
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'step_key' => $stepKey,
            'level' => $level,
            'message' => $redactor->redact($run->workspace_id, $message),
            'context' => $context === [] ? null : $redactor->redact($run->workspace_id, $context),
            'logged_at' => now(),
        ]);
    }
}
