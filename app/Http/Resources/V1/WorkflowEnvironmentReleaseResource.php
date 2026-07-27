<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowEnvironmentReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'workflow_id' => $this->workflow_id,
            'environment_id' => $this->environment_id,
            'version_id' => $this->version_id,
            'released_by' => $this->released_by,
            'notes' => $this->notes,
            'released_at' => $this->released_at,
            'created_at' => $this->created_at,
        ];
    }
}
