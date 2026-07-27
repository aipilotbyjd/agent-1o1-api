<?php

namespace Database\Factories;

use App\Models\CredentialType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CredentialType>
 */
class CredentialTypeFactory extends Factory
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
            'key' => Str::slug($name, '_'),
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'auth_type' => 'api_key',
            'color' => '#6366f1',
            'icon' => 'key',
            'docs_url' => null,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'string', 'secret' => true, 'required' => true],
            ],
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
