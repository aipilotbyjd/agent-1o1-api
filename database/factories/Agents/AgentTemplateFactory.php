<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentTemplate>
 */
class AgentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['support', 'sales', 'productivity', 'engineering']),
            'system_prompt' => $this->faker->paragraph(),
            'llm_provider' => 'anthropic',
            'llm_model' => 'claude-sonnet-4-6',
            'is_featured' => false,
            'is_active' => true,
            'usage_count' => 0,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
