<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'notes' => $this->notes,
            'published_by' => $this->published_by,
            'created_at' => $this->created_at,
            'graph' => $this->when($this->include_graph ?? true, fn (): array => $this->graph),
        ];
    }
}
