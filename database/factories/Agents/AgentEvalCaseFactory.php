<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalCase>
 */
class AgentEvalCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suite_id' => AgentEvalSuite::factory(),
            'name' => fake()->words(3, true),
            'input' => fake()->sentence(),
            'assertions' => [['type' => 'contains', 'value' => fake()->word()]],
            'sort_order' => 0,
        ];
    }
}
