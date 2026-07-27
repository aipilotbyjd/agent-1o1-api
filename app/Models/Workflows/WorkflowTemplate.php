<?php

namespace App\Models\Workflows;

use Database\Factories\Workflows\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'slug', 'description', 'category', 'icon', 'color', 'tags', 'graph',
    'is_featured', 'is_active', 'usage_count', 'sort_order',
])]
class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'graph' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
