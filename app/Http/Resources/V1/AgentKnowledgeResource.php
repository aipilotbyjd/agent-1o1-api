<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentKnowledgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'agent_id' => $this->agent_id,
            'title' => $this->title,
            'content' => $this->content,
            'source_type' => $this->source_type,
            'source_url' => $this->source_url,
            'file_path' => $this->file_path,
            'tokens' => $this->tokens,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
