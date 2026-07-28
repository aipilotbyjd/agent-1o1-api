<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentEvalRun>
 */
class AgentEvalRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suite = AgentEvalSuite::factory()->create();

        return [
            'suite_id' => $suite->id,
            'agent_id' => $suite->agent_id,
            'status' => AgentEvalRun::STATUS_COMPLETED,
            'total' => 1,
            'passed' => 1,
            'failed' => 0,
            'results' => [],
            'finished_at' => now(),
        ];
    }
}
