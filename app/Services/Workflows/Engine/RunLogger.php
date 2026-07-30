<?php

namespace App\Services\Workflows\Engine;

use App\Models\Runs\Run;
use App\Models\Runs\RunLog;
use App\Services\Runs\SecretRedactor;

/**
 * Writes a run's timeline.
 *
 * Every message goes through the redactor on the way in: log lines are built from
 * exception text and upstream response bodies, either of which can echo a credential
 * straight into a record the whole workspace can read.
 */
class RunLogger
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(Run $run, string $level, string $message, ?string $stepKey = null, array $context = []): void
    {
        RunLog::create([
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'step_key' => $stepKey,
            'level' => $level,
            'message' => $this->redact($run, $message),
            'context' => $context === [] ? null : $this->redactor->redact($run->workspace_id, $context),
            'logged_at' => now(),
        ]);
    }

    public function info(Run $run, string $message, ?string $stepKey = null): void
    {
        $this->log($run, 'info', $message, $stepKey);
    }

    public function warning(Run $run, string $message, ?string $stepKey = null): void
    {
        $this->log($run, 'warning', $message, $stepKey);
    }

    public function error(Run $run, string $message, ?string $stepKey = null): void
    {
        $this->log($run, 'error', $message, $stepKey);
    }

    /**
     * Scrub a message for storing somewhere other than a log line — a step's error
     * column, or the run's own failure reason.
     */
    public function redact(Run $run, string $message): string
    {
        return $this->redactor->redact($run->workspace_id, $message);
    }
}
