<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceEnvironment>
 */
class WorkspaceEnvironmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'workspace_id' => Workspace::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
