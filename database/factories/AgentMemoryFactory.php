<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMemory>
 */
class AgentMemoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'agent_id' => Agent::factory(),
            'user_id' => null,
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->sentence(),
            'type' => 'fact',
        ];
    }
}
