<?php

namespace App\Http\Resources\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsagePeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'credits_limit' => $this->credits_limit,
            'credits_from_packs' => $this->credits_from_packs,
            'credits_rolled_over' => $this->credits_rolled_over,
            'credits_used' => $this->credits_used,
            'credits_remaining' => $this->isUnlimited() ? null : $this->creditsRemaining(),
            'is_unlimited' => $this->isUnlimited(),
            'executions_total' => $this->executions_total,
        ];
    }
}
