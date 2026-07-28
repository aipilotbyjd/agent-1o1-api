<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPack>
 */
class CreditPackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'purchased_by' => User::factory(),
            'pack_key' => 'credits_1000',
            'credits_amount' => 1000,
            'price_cents' => 1000,
            'currency' => 'usd',
            'status' => CreditPackStatus::Pending,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
            'purchased_at' => null,
        ];
    }
}
