<?php

namespace Database\Factories\Triggers;

use App\Models\Triggers\TriggerType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TriggerType>
 */
class TriggerTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => 'webhook',
            'key' => 'test.'.fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'mechanism' => 'webhook',
            'signature_scheme' => null,
            'dedupe_header' => null,
            'dedupe_payload_path' => null,
            'preset_config' => [],
            'fields' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function githubPreset(): static
    {
        return $this->state(fn (): array => [
            'category' => 'github',
            'signature_scheme' => 'github',
            'dedupe_header' => 'X-GitHub-Delivery',
        ]);
    }

    public function stripePreset(): static
    {
        return $this->state(fn (): array => [
            'category' => 'stripe',
            'signature_scheme' => 'stripe',
            'dedupe_payload_path' => 'id',
        ]);
    }

    public function slackPreset(): static
    {
        return $this->state(fn (): array => [
            'category' => 'slack',
            'signature_scheme' => 'slack',
        ]);
    }

    public function polling(): static
    {
        return $this->state(fn (): array => [
            'category' => 'polling',
            'mechanism' => 'polling',
            'preset_config' => [
                'poll_url' => 'https://example.test/api/items',
                'poll_interval_minutes' => 5,
                'cursor_path' => 'id',
                'items_path' => 'items',
            ],
        ]);
    }
}
