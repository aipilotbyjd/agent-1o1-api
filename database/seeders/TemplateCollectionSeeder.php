<?php

namespace Database\Seeders;

use App\Models\TemplateCollection;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TemplateCollectionSeeder extends Seeder
{
    /**
     * Group the seeded workflow templates into starter collections. Must run after
     * WorkflowTemplateSeeder.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $collection) {
            $slugs = $collection['template_slugs'];
            unset($collection['template_slugs']);

            $templateIds = WorkflowTemplate::query()->whereIn('slug', $slugs)->pluck('id')->all();

            TemplateCollection::updateOrCreate(
                ['slug' => Str::slug($collection['name'])],
                [
                    ...$collection,
                    'slug' => Str::slug($collection['name']),
                    'template_ids' => $templateIds,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
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
                'name' => 'Getting started',
                'description' => 'The simplest workflows to learn the builder with.',
                'icon' => 'rocket',
                'color' => '#6366f1',
                'template_slugs' => ['wait-then-notify'],
            ],
            [
                'name' => 'Governance & control',
                'description' => 'Approvals, branching, and other control-flow patterns.',
                'icon' => 'shield-check',
                'color' => '#22c55e',
                'template_slugs' => ['approve-then-run', 'branch-on-condition'],
            ],
        ];
    }
}
