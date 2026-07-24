<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
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
            'user_id' => User::factory(),
            'event_key' => 'workspace.member_joined',
            'in_app' => true,
            'email' => false,
            'channel_ids' => null,
        ];
    }
}
