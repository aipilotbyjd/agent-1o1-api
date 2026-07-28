<?php

namespace App\Http\Resources\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'status' => $this->stripe_status,
            'active' => $this->active(),
            'on_trial' => $this->onTrial(),
            'on_grace_period' => $this->onGracePeriod(),
            'canceled' => $this->canceled(),
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
        ];
    }
}
