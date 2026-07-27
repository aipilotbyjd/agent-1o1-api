<?php

namespace Database\Factories\Agents;

use App\Models\Agents\DocumentEmbedding;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentEmbedding>
 */
class DocumentEmbeddingFactory extends Factory
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
            'collection' => 'default',
            'source' => $this->faker->url(),
            'chunk_text' => $this->faker->paragraph(),
            'embedding' => array_map(fn () => $this->faker->randomFloat(6, -1, 1), range(1, 8)),
        ];
    }
}
