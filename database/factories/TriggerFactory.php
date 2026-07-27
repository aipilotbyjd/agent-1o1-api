<?php

namespace Database\Factories;

use App\Models\Trigger;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trigger>
 */
class TriggerFactory extends Factory
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
            'triggerable_type' => (new Workflow)->getMorphClass(),
            'triggerable_id' => Workflow::factory(),
            'type' => 'webhook',
            'config' => null,
            'token' => Str::random(40),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function schedule(string $cron = '* * * * *'): static
    {
        return $this->state(fn (): array => [
            'type' => 'schedule',
            'config' => ['cron' => $cron],
            'token' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function withSigningSecret(string $secret = 'whsec_test_secret'): static
    {
        return $this->state(fn (): array => ['signing_secret' => $secret]);
    }

    public function polling(): static
    {
        return $this->state(fn (): array => [
            'type' => 'polling',
            'token' => null,
            'poll_cursor' => null,
        ]);
    }
}
