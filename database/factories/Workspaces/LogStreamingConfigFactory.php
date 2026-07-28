<?php

namespace Database\Factories\Workspaces;

use App\Models\Workspaces\LogStreamingConfig;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogStreamingConfig>
 */
class LogStreamingConfigFactory extends Factory
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
            'destination' => 'webhook',
            'endpoint' => fake()->url(),
            'headers' => ['Authorization' => 'Bearer '.fake()->sha1()],
            'is_active' => true,
        ];
    }
}
