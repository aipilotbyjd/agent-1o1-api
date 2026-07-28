<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\GitSyncConfig;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GitSyncConfig>
 */
class GitSyncConfigFactory extends Factory
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
            'provider' => 'github',
            'repository' => $this->faker->userName().'/'.$this->faker->slug(2),
            'branch' => 'main',
            'base_path' => 'workflows',
            'access_token' => Str::random(40),
            'is_active' => true,
        ];
    }
}
