<?php

namespace App\Http\Resources\V1\Workspaces;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogStreamingConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'created_by' => $this->created_by,
            'destination' => $this->destination,
            'endpoint' => $this->endpoint,
            'headers' => $this->headers,
            'is_active' => $this->is_active,
            'last_delivered_at' => $this->last_delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
