<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
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
            'created_by' => User::factory(),
            'type' => 'webhook',
            'name' => fake()->words(2, true),
            'config' => ['url' => fake()->url()],
            'is_active' => true,
        ];
    }
}
