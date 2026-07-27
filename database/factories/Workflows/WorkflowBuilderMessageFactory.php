<?php

namespace Database\Factories\Workflows;

use App\Models\Workflows\WorkflowBuilderMessage;
use App\Models\Workflows\WorkflowBuilderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowBuilderMessage>
 */
class WorkflowBuilderMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => WorkflowBuilderSession::factory(),
            'role' => 'user',
            'content' => $this->faker->sentence(),
            'processing_status' => 'completed',
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (): array => ['role' => 'assistant']);
    }
}
