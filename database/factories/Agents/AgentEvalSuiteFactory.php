<?php

namespace Database\Factories\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalSuite>
 */
class AgentEvalSuiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agent = Agent::factory()->create();

        return [
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }
}
