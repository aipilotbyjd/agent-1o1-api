<?php

namespace Database\Seeders;

use App\Models\Workflows\WorkflowTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkflowTemplateSeeder extends Seeder
{
    /**
     * Seed the starter gallery of workflow templates.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $template) {
            WorkflowTemplate::updateOrCreate(
                ['slug' => Str::slug($template['name'])],
                [...$template, 'slug' => Str::slug($template['name']), 'is_active' => true, 'sort_order' => $sort++],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Wait then notify',
                'description' => 'A minimal two-step workflow: wait, then transform the input into an output payload.',
                'category' => 'getting-started',
                'icon' => 'play',
                'color' => '#6366f1',
                'is_featured' => true,
                'graph' => [
                    'steps' => [
                        ['key' => 'wait', 'type' => 'delay', 'config' => ['seconds' => 60], 'position' => ['x' => 0, 'y' => 0]],
                        ['key' => 'build_output', 'type' => 'transform', 'config' => ['mapping' => ['message' => '{{ input.message }}']], 'position' => ['x' => 240, 'y' => 0]],
                    ],
                    'edges' => [
                        ['from' => 'wait', 'to' => 'build_output', 'condition' => null],
                    ],
                ],
            ],
            [
                'name' => 'Approve then run',
                'description' => 'Pauses for human approval before running a tool step.',
                'category' => 'governance',
                'icon' => 'check-circle',
                'color' => '#22c55e',
                'is_featured' => true,
                'graph' => [
                    'steps' => [
                        ['key' => 'review', 'type' => 'human_approval', 'config' => ['message' => 'Approve this run?'], 'position' => ['x' => 0, 'y' => 0]],
                        ['key' => 'run_tool', 'type' => 'tool', 'config' => [], 'position' => ['x' => 240, 'y' => 0]],
                    ],
                    'edges' => [
                        ['from' => 'review', 'to' => 'run_tool', 'condition' => null],
                    ],
                ],
            ],
            [
                'name' => 'Branch on condition',
                'description' => 'Routes to one of two transform steps depending on a condition.',
                'category' => 'flow-control',
                'icon' => 'git-branch',
                'color' => '#f59e0b',
                'is_featured' => false,
                'graph' => [
                    'steps' => [
                        ['key' => 'decide', 'type' => 'condition', 'config' => ['expression' => '{{ input.approved }}'], 'position' => ['x' => 0, 'y' => 0]],
                        ['key' => 'on_true', 'type' => 'transform', 'config' => ['mapping' => ['status' => 'approved']], 'position' => ['x' => 240, 'y' => -80]],
                        ['key' => 'on_false', 'type' => 'transform', 'config' => ['mapping' => ['status' => 'rejected']], 'position' => ['x' => 240, 'y' => 80]],
                    ],
                    'edges' => [
                        ['from' => 'decide', 'to' => 'on_true', 'condition' => 'true'],
                        ['from' => 'decide', 'to' => 'on_false', 'condition' => 'false'],
                    ],
                ],
            ],
        ];
    }
}
