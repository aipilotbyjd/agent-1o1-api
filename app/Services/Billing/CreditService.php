<?php

namespace App\Services\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Exceptions\Billing\InsufficientCreditsException;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Debits credits from the workspace's current usage period.
     *
     * Locks the usage period row for the duration of the transaction so concurrent
     * workflow runs cannot both read a stale balance and overspend it.
     *
     * @throws InsufficientCreditsException
     */
    public function consume(Workspace $workspace, int $amount, ?Model $subject = null, ?string $description = null): CreditTransaction
    {
        return DB::transaction(function () use ($workspace, $amount, $subject, $description) {
            $period = UsagePeriod::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $period->isUnlimited() && $period->creditsRemaining() < $amount) {
                throw new InsufficientCreditsException($amount, $period->creditsRemaining());
            }

            if (! $period->isUnlimited()) {
                $period->increment('credits_used', $amount);
            }

            $period->increment('executions_total');

            return $this->recordTransaction(
                $workspace,
                $period,
                CreditTransactionType::Consume,
                -$amount,
                $subject,
                $description,
            );
        });
    }

    /**
     * Grants credits to the workspace's current usage period (e.g. a purchased credit pack).
     */
    public function grant(Workspace $workspace, int $amount, ?Model $subject = null, ?string $description = null): CreditTransaction
    {
        return DB::transaction(function () use ($workspace, $amount, $subject, $description) {
            $period = UsagePeriod::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->firstOrFail();

            $period->increment('credits_from_packs', $amount);

            return $this->recordTransaction(
                $workspace,
                $period,
                CreditTransactionType::Grant,
                $amount,
                $subject,
                $description,
            );
        });
    }

    private function recordTransaction(
        Workspace $workspace,
        UsagePeriod $period,
        CreditTransactionType $type,
        int $signedAmount,
        ?Model $subject,
        ?string $description,
    ): CreditTransaction {
        $period->refresh();

        return CreditTransaction::create([
            'workspace_id' => $workspace->id,
            'usage_period_id' => $period->id,
            'type' => $type,
            'amount' => $signedAmount,
            'balance_after' => $period->isUnlimited() ? -1 : $period->creditsRemaining(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
        ]);
    }
}
