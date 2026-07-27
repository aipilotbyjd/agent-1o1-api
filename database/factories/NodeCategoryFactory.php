<?php

namespace Database\Factories;

use App\Models\NodeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NodeCategory>
 */
class NodeCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'circle',
            'color' => '#6366f1',
            'sort_order' => fake()->numberBetween(0, 20),
            'kind' => 'core',
        ];
    }
}
