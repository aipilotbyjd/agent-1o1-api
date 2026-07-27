<?php

namespace Database\Factories;

use App\Models\Credential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
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
            'name' => fake()->unique()->words(2, true).' key',
            'type' => 'api_key',
            'data' => ['api_key' => Str::random(32)],
            'created_by' => User::factory(),
        ];
    }
}
