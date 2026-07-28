<?php

namespace App\Models\Runs;

use App\Enums\Runs\RunStepStatus;
use App\Events\RunStepUpdated;
use Database\Factories\Runs\RunStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'run_id', 'key', 'type', 'status', 'input', 'output',
    'error', 'usage', 'started_at', 'finished_at',
    'attempt', 'max_attempts', 'retry_delay_seconds', 'loop_index',
])]
class RunStep extends Model
{
    /** @use HasFactory<RunStepFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempt' => 1,
        'max_attempts' => 1,
        'retry_delay_seconds' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStepStatus::class,
            'input' => 'array',
            'output' => 'array',
            'usage' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'retry_delay_seconds' => 'integer',
            'loop_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function markRunning(): void
    {
        $this->transitionTo(RunStepStatus::Running, ['started_at' => now()]);
    }

    /**
     * @param  array<string, mixed>|null  $output
     * @param  array<string, mixed>|null  $usage
     */
    public function markCompleted(?array $output = null, ?array $usage = null): void
    {
        $this->transitionTo(RunStepStatus::Completed, [
            'output' => $output,
            'usage' => $usage,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->transitionTo(RunStepStatus::Failed, ['error' => $error, 'finished_at' => now()]);
    }

    public function markAwaitingApproval(): void
    {
        $this->transitionTo(RunStepStatus::AwaitingApproval);
    }

    public function markSkipped(): void
    {
        $this->transitionTo(RunStepStatus::Skipped, ['finished_at' => now()]);
    }

    public function markCancelled(): void
    {
        $this->transitionTo(RunStepStatus::Cancelled, ['finished_at' => now()]);
    }

    /**
     * Reset a failed step back to pending for another attempt.
     */
    public function scheduleRetry(string $error): void
    {
        $this->transitionTo(RunStepStatus::Pending, [
            'attempt' => $this->attempt + 1,
            'error' => $error,
        ]);
    }

    public function canRetry(): bool
    {
        return $this->attempt < $this->max_attempts;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transitionTo(RunStepStatus $status, array $attributes = []): void
    {
        $this->update([...$attributes, 'status' => $status]);

        RunStepUpdated::dispatch($this);
    }
}
