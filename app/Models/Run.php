<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Events\RunUpdated;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'workspace_id', 'runnable_type', 'runnable_id', 'workflow_version_id', 'agent_version',
    'status', 'trigger_type', 'input', 'output', 'error', 'triggered_by', 'started_at', 'finished_at',
])]
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function runnable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    /**
     * @return HasMany<RunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RunStep::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function markRunning(): void
    {
        $this->transitionTo(RunStatus::Running, ['started_at' => now()]);
    }

    /**
     * @param  array<string, mixed>|null  $output
     */
    public function markCompleted(?array $output = null): void
    {
        $this->transitionTo(RunStatus::Completed, ['output' => $output, 'finished_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->transitionTo(RunStatus::Failed, ['error' => $error, 'finished_at' => now()]);
    }

    public function markAwaitingApproval(): void
    {
        $this->transitionTo(RunStatus::AwaitingApproval);
    }

    public function cancel(): void
    {
        $this->steps()
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Running->value, RunStatus::AwaitingApproval->value])
            ->get()
            ->each(fn (RunStep $step) => $step->markCancelled());

        $this->transitionTo(RunStatus::Cancelled, ['finished_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function transitionTo(RunStatus $status, array $attributes = []): void
    {
        $this->update([...$attributes, 'status' => $status]);

        RunUpdated::dispatch($this);
    }
}
