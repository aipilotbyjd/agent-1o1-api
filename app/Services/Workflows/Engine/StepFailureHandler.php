<?php

namespace App\Services\Workflows\Engine;

use App\Jobs\Workflows\RetryWorkflowStep;
use App\Models\Runs\Run;
use App\Models\Runs\RunStep;
use App\Services\Workflows\StepOptions;

/**
 * Decides what a failed step means for the run: another attempt, a route down an error
 * edge, or the end of the line.
 */
class StepFailureHandler
{
    private const MAX_BACKOFF_SECONDS = 3600;

    public function __construct(
        private readonly GraphAdvancer $advancer,
        private readonly RunLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $step
     */
    public function handle(Run $run, RunStep $runStep, array $step, string $message): void
    {
        // Failure messages are built from exception text and upstream response bodies,
        // either of which can echo a credential straight back into the run record.
        $message = $this->logger->redact($run, $message);

        if ($runStep->canRetry()) {
            $delay = $this->backoffSeconds($runStep->retry_delay_seconds, $runStep->attempt);
            $this->logger->warning($run, "Step [{$step['key']}] failed, retrying in {$delay}s: ".$message, $step['key']);
            $runStep->scheduleRetry($message);

            RetryWorkflowStep::dispatch($run->id, $runStep->id)
                ->delay($delay > 0 ? now()->addSeconds($delay) : null);

            return;
        }

        $this->logger->error($run, "Step [{$step['key']}] failed: ".$message, $step['key']);
        $runStep->markFailed($message);

        $hasErrorEdge = $this->advancer->hasErrorEdge($run, $step['key']);
        $continueOnError = StepOptions::fromStep($step)->continueOnError;

        // A failure with somewhere to go is a route, not the end of the run — the step
        // stays failed, but the graph carries on down its error path.
        if ($hasErrorEdge || $continueOnError) {
            $this->advancer->advance(
                $run,
                $step,
                ['result' => GraphAdvancer::ERROR_CONDITION, 'error' => $message],
                failed: true,
            );

            return;
        }

        $run->markFailed("Step [{$step['key']}] failed: ".$message);
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
}
