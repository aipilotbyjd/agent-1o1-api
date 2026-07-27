<?php

namespace App\Http\Resources\V1\Agents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentMemoryResource extends JsonResource
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
            'user_id' => $this->user_id,
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
