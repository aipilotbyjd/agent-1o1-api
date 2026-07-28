<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Variable;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Variable>
 */
class VariableFactory extends Factory
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
            'key' => Str::slug(fake()->unique()->words(2, true), '_'),
            'value' => fake()->sentence(),
            'is_secret' => false,
            'created_by' => User::factory(),
        ];
    }

    public function secret(): static
    {
        return $this->state(fn (): array => ['is_secret' => true]);
    }
}
