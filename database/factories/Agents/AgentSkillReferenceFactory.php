<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentSkill;
use App\Models\Agents\AgentSkillReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSkillReference>
 */
class AgentSkillReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_id' => AgentSkill::factory(),
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraphs(2, true),
            'sort_order' => 0,
        ];
    }
}
