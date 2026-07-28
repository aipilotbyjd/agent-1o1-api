<?php

namespace Database\Factories;

use App\Models\Tool;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' lookup';

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => Str::slug($name, '_'),
            'description' => fake()->sentence(),
            'type' => 'http',
            'config' => [
                'url' => 'https://api.example.com/lookup',
                'method' => 'GET',
                'parameters' => [
                    ['name' => 'query', 'type' => 'string', 'description' => 'Search query', 'required' => true],
                ],
            ],
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
