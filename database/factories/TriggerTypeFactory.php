<?php

namespace Database\Factories;

use App\Models\TriggerType;
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
            'preset_config' => [],
            'fields' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
