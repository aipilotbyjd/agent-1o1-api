<?php

namespace Database\Factories\Billing;

use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsagePeriod>
 */
class UsagePeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'subscription_id' => null,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'credits_limit' => 1000,
            'credits_from_packs' => 0,
            'credits_rolled_over' => 0,
            'credits_used' => 0,
            'executions_total' => 0,
            'is_current' => true,
        ];
    }
}
