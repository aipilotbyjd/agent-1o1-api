<?php

namespace Database\Factories;

use App\Models\Trigger;
use App\Models\TriggerEvent;
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
            'matched' => true,
        ];
    }
}
