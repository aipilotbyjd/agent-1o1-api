<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\Limit;
use App\Models\Billing\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => 2900,
            'price_yearly' => 29000,
            'limits' => [
                Limit::CreditsMonthly->value => 1000,
                Limit::Workflows->value => -1,
                Limit::Agents->value => -1,
                Limit::Members->value => 5,
                Limit::Environments->value => 3,
            ],
            'features' => [],
            'stripe_product_id' => null,
            'stripe_price_id_monthly' => null,
            'stripe_price_id_yearly' => null,
            'trial_days' => 14,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
