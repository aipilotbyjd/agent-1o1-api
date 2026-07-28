<?php

namespace App\Http\Resources\V1\Workflows;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'color' => $this->color,
            'workflows_count' => $this->whenCounted('workflows'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
