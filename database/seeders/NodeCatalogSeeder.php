<?php

namespace Database\Seeders;

use App\Models\Nodes\Node;
use App\Models\Nodes\NodeCategory;
use Illuminate\Database\Seeder;

class NodeCatalogSeeder extends Seeder
{
    /**
     * Seed the builtin node category and node catalog that backs the
     * workflow builder's palette. One entry per WorkflowStepType-backed
     * capability the engine actually knows how to execute.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->categories() as $category) {
            $categories[$category['slug']] = NodeCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => $sort++],
            );
        }

        foreach ($this->nodes() as $node) {
            $categorySlug = $node['category'];
            unset($node['category']);

            Node::updateOrCreate(
                ['type' => $node['type']],
                [
                    ...$node,
                    'category_id' => $categories[$categorySlug]->id,
                    'workspace_id' => null,
                    'is_custom' => false,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return [
            ['slug' => 'flow-control', 'name' => 'Flow Control', 'icon' => 'git-branch', 'color' => '#f59e0b', 'kind' => 'core',
                'description' => 'Route, branch, and join execution.'],
            ['slug' => 'actions', 'name' => 'Actions', 'icon' => 'zap', 'color' => '#6366f1', 'kind' => 'core',
                'description' => 'Call tools and external services.'],
            ['slug' => 'ai', 'name' => 'AI', 'icon' => 'sparkles', 'color' => '#8b5cf6', 'kind' => 'core',
                'description' => 'Prompt an agent.'],
            ['slug' => 'data', 'name' => 'Data', 'icon' => 'shuffle', 'color' => '#0ea5e9', 'kind' => 'core',
                'description' => 'Transform and shape data.'],
            ['slug' => 'human', 'name' => 'Human in the Loop', 'icon' => 'user-check', 'color' => '#ec4899', 'kind' => 'core',
                'description' => 'Pause for a human decision.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nodes(): array
    {
        return [
            ['category' => 'ai', 'step_type' => 'agent', 'type' => 'agent', 'version' => 1,
                'name' => 'Agent', 'description' => 'Prompt an agent and capture its response.',
                'icon' => 'sparkles', 'color' => '#8b5cf6',
                'config_schema' => ['type' => 'object', 'required' => ['agent_id'], 'properties' => [
                    'agent_id' => ['type' => 'integer'],
                    'prompt' => ['type' => 'string'],
                ]]],
            ['category' => 'actions', 'step_type' => 'tool', 'type' => 'tool', 'version' => 1,
                'name' => 'Tool', 'description' => 'Call a configured workspace tool.',
                'icon' => 'wrench', 'color' => '#6366f1',
                'config_schema' => ['type' => 'object', 'required' => ['tool_id'], 'properties' => [
                    'tool_id' => ['type' => 'integer'],
                    'arguments' => ['type' => 'object'],
                ]]],
            ['category' => 'flow-control', 'step_type' => 'condition', 'type' => 'condition', 'version' => 1,
                'name' => 'Condition', 'description' => 'Branch based on a comparison.',
                'icon' => 'split', 'color' => '#f59e0b',
                'config_schema' => ['type' => 'object', 'required' => ['field'], 'properties' => [
                    'field' => ['type' => 'string'],
                    'operator' => ['type' => 'string'],
                    'value' => ['type' => 'string'],
                ]]],
            ['category' => 'flow-control', 'step_type' => 'merge', 'type' => 'merge', 'version' => 1,
                'name' => 'Merge', 'description' => 'Wait for all incoming branches, then continue.',
                'icon' => 'git-merge', 'color' => '#f59e0b',
                'config_schema' => ['type' => 'object', 'properties' => []]],
            ['category' => 'flow-control', 'step_type' => 'sub_workflow', 'type' => 'sub_workflow', 'version' => 1,
                'name' => 'Sub-workflow', 'description' => 'Run another workflow and wait for it to finish.',
                'icon' => 'workflow', 'color' => '#f59e0b',
                'config_schema' => ['type' => 'object', 'required' => ['workflow_id'], 'properties' => [
                    'workflow_id' => ['type' => 'integer'],
                    'input' => ['type' => 'object'],
                ]]],
            ['category' => 'flow-control', 'step_type' => 'loop', 'type' => 'loop', 'version' => 1,
                'name' => 'Loop', 'description' => 'Map a template over each item in a list.',
                'icon' => 'repeat', 'color' => '#f59e0b',
                'config_schema' => ['type' => 'object', 'required' => ['items'], 'properties' => [
                    'items' => ['type' => 'string'],
                    'mapping' => ['type' => 'object'],
                ]]],
            ['category' => 'data', 'step_type' => 'transform', 'type' => 'transform', 'version' => 1,
                'name' => 'Transform', 'description' => 'Map templated values into the run context.',
                'icon' => 'shuffle', 'color' => '#0ea5e9',
                'config_schema' => ['type' => 'object', 'properties' => ['mapping' => ['type' => 'object']]]],
            ['category' => 'flow-control', 'step_type' => 'delay', 'type' => 'delay', 'version' => 1,
                'name' => 'Delay', 'description' => 'Pause before continuing to the next step.',
                'icon' => 'clock', 'color' => '#f59e0b',
                'config_schema' => ['type' => 'object', 'properties' => ['seconds' => ['type' => 'integer']]]],
            ['category' => 'human', 'step_type' => 'human_approval', 'type' => 'human_approval', 'version' => 1,
                'name' => 'Human Approval', 'description' => 'Pause and notify workspace admins for a decision.',
                'icon' => 'user-check', 'color' => '#ec4899',
                'config_schema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]],
        ];
    }
}
