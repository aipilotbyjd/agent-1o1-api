<?php

namespace App\Services\Workflows\Nodes;

use App\Models\Nodes\Node;
use App\Models\Nodes\NodeCategory;

class NodeCatalogSync
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * Project the code registry into the `nodes` table.
     *
     * The table exists so the palette can be queried, filtered, and joined like any
     * other data — but the registry is the source of truth, so builtin rows are
     * upserted from it here. Workspace-authored custom nodes are left untouched.
     *
     * @return array{categories: int, nodes: int, retired: int}
     */
    public function sync(): array
    {
        $categories = $this->syncCategories();

        foreach ($this->registry->all() as $definition) {
            Node::updateOrCreate(
                ['type' => $definition->type()],
                [
                    'category_id' => $categories[$definition->category()]->id,
                    'workspace_id' => null,
                    'step_type' => $definition->stepType(),
                    'version' => $definition->version(),
                    'name' => $definition->name(),
                    'description' => $definition->description(),
                    'icon' => $definition->icon(),
                    'color' => $definition->color(),
                    'config_schema' => $definition->configSchema(),
                    'input_schema' => null,
                    'output_schema' => $definition->outputSchema() === [] ? null : $definition->outputSchema(),
                    'credential_type' => $definition->credentialType(),
                    'docs_url' => $definition->docsUrl(),
                    'is_custom' => false,
                    'is_premium' => $definition->isPremium(),
                    'is_active' => true,
                ],
            );
        }

        return [
            'categories' => count($categories),
            'nodes' => count($this->registry->all()),
            'retired' => $this->retireRemovedNodes(),
        ];
    }

    /**
     * Deactivate builtin rows whose definition has since been deleted from the registry.
     *
     * Upserting alone would leave a removed node sitting in the palette as an active row
     * forever, offering authors a node the engine can no longer run. The rows are kept
     * rather than deleted so existing graphs that reference the type still resolve.
     */
    private function retireRemovedNodes(): int
    {
        return Node::query()
            ->where('is_custom', false)
            ->where('is_active', true)
            ->whereNotIn('type', array_keys($this->registry->all()))
            ->update(['is_active' => false]);
    }

    /**
     * @return array<string, NodeCategory>
     */
    private function syncCategories(): array
    {
        $categories = [];
        $sort = 0;

        foreach ($this->categories() as $category) {
            $categories[$category['slug']] = NodeCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'sort_order' => $sort++],
            );
        }

        return $categories;
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
}
