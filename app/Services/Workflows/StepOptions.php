<?php

namespace App\Services\Workflows;

/**
 * The engine-level settings any step may carry in its config.
 *
 * These keys are the engine's, not the node's: the runner reads them to size a step's
 * retries and timeout, the failure handler reads them to decide whether a failure ends
 * the run, and the tool handler strips them so a connector only sees its own config.
 * That last list had to be kept in step with the other two by hand, which is exactly the
 * kind of agreement that quietly stops being true.
 */
final class StepOptions
{
    /**
     * Keys the engine consumes, plus `node`, which names the connector to run.
     */
    public const RESERVED_KEYS = [
        'node',
        'max_attempts',
        'retry_delay_seconds',
        'timeout_seconds',
        'continue_on_error',
    ];

    private function __construct(
        public readonly int $maxAttempts,
        public readonly int $retryDelaySeconds,
        public readonly int $timeoutSeconds,
        public readonly bool $continueOnError,
    ) {}

    /**
     * @param  array<string, mixed>  $step
     */
    public static function fromStep(array $step): self
    {
        $config = $step['config'] ?? [];

        return new self(
            maxAttempts: max(1, (int) ($config['max_attempts'] ?? 1)),
            retryDelaySeconds: (int) ($config['retry_delay_seconds'] ?? 0),
            timeoutSeconds: (int) ($config['timeout_seconds'] ?? 0),
            continueOnError: (bool) ($config['continue_on_error'] ?? false),
        );
    }

    /**
     * A step's config with the engine's own keys removed, so a connector is handed only
     * the fields its schema declares.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function stripReserved(array $config): array
    {
        return array_diff_key($config, array_flip(self::RESERVED_KEYS));
    }

    public function hasTimeout(): bool
    {
        return $this->timeoutSeconds > 0;
    }
}
