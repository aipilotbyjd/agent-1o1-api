<?php

namespace App\Services\Workflows\Engine;

use App\Enums\Runs\RunStepStatus;
use App\Enums\Workflows\WorkflowStepType;
use App\Jobs\Workflows\ExecuteWorkflowStep;
use App\Models\Runs\Run;
use App\Services\Workflows\WorkflowGraph;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Moves a run along its graph once a step has settled.
 *
 * Everything here is about edges rather than work: which successors a given outcome
 * unlocks, which branches can never be reached and must be marked skipped, and whether
 * the run as a whole has anything left in flight.
 */
class GraphAdvancer
{
    /**
     * The edge condition that routes a failed step somewhere other than the end of the run.
     */
    public const ERROR_CONDITION = 'error';

    public function __construct(private readonly RunLogger $logger) {}

    public function graphFor(Run $run): WorkflowGraph
    {
        return WorkflowGraph::fromArray($run->workflowVersion?->graph);
    }

    /**
     * Dispatch the next steps whose edge conditions match, or finish the run.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $output
     */
    public function advance(Run $run, array $step, array $output, bool $failed = false): void
    {
        $graph = $this->graphFor($run);
        $delaySeconds = ($step['type'] ?? null) === WorkflowStepType::Delay->value
            ? (int) ($output['seconds'] ?? 0)
            : 0;

        $outgoing = $graph->outgoingFrom($step['key']);

        // Dead branches are settled *before* the live ones are dispatched. A dispatched
        // step can run all the way to a downstream merge synchronously, and that merge
        // must already be able to see which of its branches will never arrive.
        foreach ($outgoing as $edge) {
            if (! $this->edgeMatches($edge, $output, $failed)) {
                $this->skipUnreachable($run, $graph, $edge['to']);
            }
        }

        $dispatched = false;

        foreach ($outgoing as $edge) {
            if (! $this->edgeMatches($edge, $output, $failed)) {
                continue;
            }

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
     * Whether the step has an outgoing `error` edge to route a failure down.
     */
    public function hasErrorEdge(Run $run, string $stepKey): bool
    {
        return $this->graphFor($run)->hasOutgoingCondition($stepKey, self::ERROR_CONDITION);
    }

    /**
     * Whether every one of the given steps has settled.
     *
     * @param  array<int, string>  $stepKeys
     */
    public function allComplete(Run $run, array $stepKeys): bool
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
     */
    private function skipUnreachable(Run $run, WorkflowGraph $graph, string $stepKey): void
    {
        if ($run->steps()->where('key', $stepKey)->exists()) {
            return;
        }

        $predecessorKeys = $graph->predecessorKeys($stepKey);

        foreach ($predecessorKeys as $predecessorKey) {
            $predecessor = $run->steps()->where('key', $predecessorKey)->first();

            // A predecessor that has not run, or is still running, may yet dispatch it.
            if ($predecessor === null || ! $predecessor->status->isTerminal()) {
                return;
            }

            if ($predecessor->status === RunStepStatus::Completed || $predecessor->status === RunStepStatus::Failed) {
                $edge = $graph->edgeBetween($predecessorKey, $stepKey);

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
            $runStep = $run->steps()->create([
                'key' => $stepKey,
                'type' => $graph->step($stepKey)['type'] ?? 'transform',
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $runStep->markSkipped();
        $this->logger->info($run, "Step [{$stepKey}] was skipped — no branch reaches it.", $stepKey);

        foreach ($graph->successorKeys($stepKey) as $next) {
            $this->skipUnreachable($run, $graph, $next);
        }
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
                    RunStepStatus::AwaitingCallback->value,
                ])
                ->exists();

            if ($inFlight) {
                return;
            }

            $this->logger->info($locked, 'Run completed.');
            $locked->markCompleted($lastOutput);
        });

        $run->refresh();
    }
}
