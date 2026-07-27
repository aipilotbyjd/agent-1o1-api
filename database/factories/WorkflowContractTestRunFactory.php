<?php

namespace Database\Factories;

use App\Models\WorkflowContractSnapshot;
use App\Models\WorkflowContractTestRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowContractTestRun>
 */
class WorkflowContractTestRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => WorkflowContractSnapshot::factory(),
            'status' => 'passed',
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => 'failed']);
    }
}
