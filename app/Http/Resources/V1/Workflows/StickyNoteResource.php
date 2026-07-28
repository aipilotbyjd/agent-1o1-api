<?php

namespace App\Http\Resources\V1\Workflows;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StickyNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workspace_id' => $this->workspace_id,
            'created_by' => $this->created_by,
            'content' => $this->content,
            'color' => $this->color,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'width' => $this->width,
            'height' => $this->height,
            'z_index' => $this->z_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
