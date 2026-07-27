<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle().' Assistant';

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(),
            'instructions' => 'You are a helpful assistant. '.fake()->sentence(),
            'provider' => 'anthropic',
            'model' => null,
            'temperature' => null,
            'settings' => null,
            'created_by' => User::factory(),
        ];
    }
}
