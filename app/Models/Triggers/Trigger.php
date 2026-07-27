<?php

namespace App\Models\Triggers;

use App\Models\Credentials\Credential;
use App\Models\Workspaces\Workspace;
use Database\Factories\Triggers\TriggerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'workspace_id', 'triggerable_type', 'triggerable_id', 'type', 'trigger_type_id',
    'config', 'token', 'signing_secret', 'consecutive_failure_count', 'credential_id',
    'poll_cursor', 'is_active', 'last_run_at', 'created_by',
])]
class Trigger extends Model
{
    /** @use HasFactory<TriggerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'signing_secret' => 'encrypted',
            'poll_cursor' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
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
    public function triggerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<TriggerType, $this>
     */
    public function triggerType(): BelongsTo
    {
        return $this->belongsTo(TriggerType::class);
    }

    /**
     * @return BelongsTo<Credential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    /**
     * @return HasMany<TriggerEvent, $this>
     */
    public function triggerEvents(): HasMany
    {
        return $this->hasMany(TriggerEvent::class);
    }

    public function hasSigningSecret(): bool
    {
        return $this->signing_secret !== null;
    }

    /**
     * Increment the failure streak, auto-disabling the trigger once it hits the
     * configured threshold so a broken target can't be hammered indefinitely.
     */
    public function registerFailure(): void
    {
        $count = $this->consecutive_failure_count + 1;

        $this->update([
            'consecutive_failure_count' => $count,
            'is_active' => $count >= (int) config('triggers.max_consecutive_failures') ? false : $this->is_active,
        ]);
    }
}
