<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentSkill;
use App\Models\Agents\AgentSkillScript;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSkillScript>
 */
class AgentSkillScriptFactory extends Factory
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
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'language' => 'php',
            'code' => "<?php\n\nreturn true;\n",
            'is_enabled' => true,
        ];
    }
}
