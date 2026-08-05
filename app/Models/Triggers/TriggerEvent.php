<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Runs\Run;
use Database\Factories\Triggers\TriggerEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'trigger_id', 'source', 'status', 'run_id', 'payload', 'payload_snippet',
    'headers', 'error', 'delivery_id', 'attempts', 'duplicate_count', 'processed_at',
])]
class TriggerEvent extends Model
{
    /** @use HasFactory<TriggerEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TriggerEventStatus::class,
            'payload' => 'array',
            'headers' => 'array',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trigger, $this>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * Kept as a derived value so API consumers written against the old boolean
     * column keep working. Status is the single source of truth.
     *
     * @return Attribute<bool, never>
     */
    protected function matched(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === TriggerEventStatus::Matched);
    }

    /**
     * Claim this event for processing, recording the attempt. Returns false when
     * another worker already moved it out of a queueable state — the guard that
     * makes a re-dispatched or duplicated job a no-op rather than a second run.
     */
    public function claim(): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', TriggerEventStatus::unresolved())
            ->update([
                'status' => TriggerEventStatus::Processing,
                'attempts' => $this->attempts + 1,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $this->refresh();

        return true;
    }

    public function markMatched(Run $run): void
    {
        $this->update([
            'status' => TriggerEventStatus::Matched,
            'run_id' => $run->id,
            'error' => null,
            'processed_at' => now(),
        ]);
    }

    public function markResolved(TriggerEventStatus $status, ?string $error = null): void
    {
        $this->update([
            'status' => $status,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }

    /**
     * Whether this event has been sitting in a non-terminal state long enough to
     * assume the worker that owned it is never coming back.
     */
    public function isStranded(Carbon $pendingBefore, Carbon $processingBefore): bool
    {
        return match ($this->status) {
            TriggerEventStatus::Pending => $this->created_at->lt($pendingBefore),
            TriggerEventStatus::Processing => $this->updated_at?->lt($processingBefore) ?? true,
            default => false,
        };
    }
}
