<?php

namespace Database\Factories\Workflows;

use App\Models\User;
use App\Models\Workflows\StickyNote;
use App\Models\Workflows\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StickyNote>
 */
class StickyNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflow = Workflow::factory()->create();

        return [
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'created_by' => User::factory(),
            'content' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'position_x' => fake()->randomFloat(2, 0, 1000),
            'position_y' => fake()->randomFloat(2, 0, 1000),
            'width' => 200,
            'height' => 120,
            'z_index' => 0,
        ];
    }
}
