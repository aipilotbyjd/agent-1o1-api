<?php

namespace Database\Factories\Runs;

use App\Models\Runs\Run;
use App\Models\Runs\RunFixSuggestion;
use App\Models\Workflows\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunFixSuggestion>
 */
class RunFixSuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflow = Workflow::factory()->create();
        $run = Run::factory()->create([
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
        ]);

        return [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'step_key' => 'step_'.fake()->word(),
            'step_type' => 'tool',
            'diagnosis' => fake()->sentence(),
            'suggestions' => [
                [
                    'title' => 'Increase timeout',
                    'description' => 'The request timed out; raise the timeout.',
                    'fix_config' => ['timeout' => 30],
                ],
            ],
            'status' => RunFixSuggestion::STATUS_PENDING,
        ];
    }
}
