<?php

namespace App\Http\Resources\V1\Agents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentSkillReferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'title' => $this->title,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
