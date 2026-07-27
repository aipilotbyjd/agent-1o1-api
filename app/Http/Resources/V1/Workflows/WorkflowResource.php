<?php

namespace App\Http\Resources\V1\Workflows;

use App\Models\Workflows\WorkflowStepEdge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'current_version' => $this->whenLoaded('currentVersion', fn (): ?int => $this->currentVersion?->version),
            'has_unpublished_changes' => $this->has_unpublished_changes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'steps' => WorkflowStepResource::collection($this->whenLoaded('steps')),
            'edges' => $this->whenLoaded('edges', fn () => $this->edges->map(fn (WorkflowStepEdge $edge): array => [
                'from' => $edge->fromStep->key,
                'to' => $edge->toStep->key,
                'condition' => $edge->condition,
            ])),
        ];
    }
}
