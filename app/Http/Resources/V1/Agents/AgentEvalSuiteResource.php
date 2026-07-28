<?php

namespace App\Http\Resources\V1\Agents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentEvalSuiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'workspace_id' => $this->workspace_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'description' => $this->description,
            'cases_count' => $this->whenCounted('cases'),
            'cases' => AgentEvalCaseResource::collection($this->whenLoaded('cases')),
            'runs' => AgentEvalRunResource::collection($this->whenLoaded('runs')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
