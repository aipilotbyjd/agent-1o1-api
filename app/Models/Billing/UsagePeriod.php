<?php

namespace App\Models\Billing;

use App\Models\Workspaces\Workspace;
use Database\Factories\Billing\UsagePeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'subscription_id', 'period_start', 'period_end', 'credits_limit', 'credits_from_packs', 'credits_rolled_over', 'credits_used', 'executions_total', 'is_current'])]
class UsagePeriod extends Model
{
    /** @use HasFactory<UsagePeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'is_current' => 'boolean',
            'credits_limit' => 'integer',
            'credits_from_packs' => 'integer',
            'credits_rolled_over' => 'integer',
            'credits_used' => 'integer',
            'executions_total' => 'integer',
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
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /** -1 limit means unlimited — callers must check isUnlimited() before relying on this value. */
    public function totalAvailable(): int
    {
        if ($this->credits_limit === -1) {
            return PHP_INT_MAX;
        }

        return $this->credits_limit + $this->credits_from_packs + $this->credits_rolled_over;
    }

    public function creditsRemaining(): int
    {
        if ($this->credits_limit === -1) {
            return PHP_INT_MAX;
        }

        return max(0, $this->totalAvailable() - $this->credits_used);
    }

    public function isUnlimited(): bool
    {
        return $this->credits_limit === -1;
    }
}
