<?php

namespace Database\Factories\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TriggerEvent>
 */
class TriggerEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trigger_id' => Trigger::factory(),
            'source' => 'webhook',
            'status' => TriggerEventStatus::Matched,
            'processed_at' => now(),
        ];
    }

    /**
     * Accepted but not yet worked — what the reconciler looks for.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => TriggerEventStatus::Pending,
            'processed_at' => null,
        ]);
    }

    /**
     * Claimed by a worker that never came back.
     */
    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => TriggerEventStatus::Processing,
            'attempts' => 1,
            'processed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => TriggerEventStatus::Failed,
            'error' => 'Processing failed.',
        ]);
    }
}
