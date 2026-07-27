<?php

namespace App\Http\Resources\V1\Agents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'color' => $this->color,
            'tags' => $this->tags,
            'avatar_url' => $this->avatar_url,
            'system_prompt' => $this->system_prompt,
            'llm_provider' => $this->llm_provider,
            'llm_model' => $this->llm_model,
            'llm_settings' => $this->llm_settings,
            'tool_configs' => $this->tool_configs,
            'example_conversations' => $this->example_conversations,
            'instructions' => $this->instructions,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
