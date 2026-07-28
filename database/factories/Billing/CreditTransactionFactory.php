<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'usage_period_id' => UsagePeriod::factory(),
            'type' => CreditTransactionType::Consume,
            'amount' => -10,
            'balance_after' => 990,
            'subject_type' => null,
            'subject_id' => null,
            'description' => null,
        ];
    }
}
