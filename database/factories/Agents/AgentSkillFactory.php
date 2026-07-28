<?php

namespace Database\Factories\Agents;

use App\Models\Agents\AgentSkill;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentSkill>
 */
class AgentSkillFactory extends Factory
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
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'instructions' => $this->faker->paragraphs(2, true),
            'is_shared' => false,
            'version' => 1,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (): array => ['is_shared' => true]);
    }
}
