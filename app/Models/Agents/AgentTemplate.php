<?php

namespace App\Models\Agents;

use Database\Factories\Agents\AgentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'slug', 'description', 'category', 'icon', 'color', 'tags', 'avatar_url',
    'system_prompt', 'llm_provider', 'llm_model', 'llm_settings', 'tool_configs',
    'example_conversations', 'instructions', 'is_featured', 'is_active', 'usage_count', 'sort_order',
])]
class AgentTemplate extends Model
{
    /** @use HasFactory<AgentTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'llm_settings' => 'array',
            'tool_configs' => 'array',
            'example_conversations' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
