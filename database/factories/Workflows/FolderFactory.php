<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\Folder;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'position' => 0,
        ];
    }
}
